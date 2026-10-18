<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\SignalStop;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Logger\TestLogger;

/**
 * @covers \Tomaj\Hermes\Shutdown\SignalStop
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Driver\ShutdownTrait
 */
#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
class SignalStopTest extends TestCase
{
    /** @var list<array{0: string, 1: callable|int}> */
    private array $handlerSeen = [];

    private bool $async = false;

    #[\Override]
    protected function setUp(): void
    {
        // PHPUnit itself may switch async signals on; start every test from synchronous delivery
        $this->async = pcntl_async_signals(false);
    }

    #[\Override]
    protected function tearDown(): void
    {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_async_signals($this->async);
    }

    public function testSigtermFinishesTheCurrentMessageAndStops(): void
    {
        $this->assertSignalStops(SIGTERM, 'SIGTERM');
    }

    public function testSigintFinishesTheCurrentMessageAndStops(): void
    {
        $this->assertSignalStops(SIGINT, 'SIGINT');
    }

    public function testPreviousHandlersAndAsyncModeAreRestored(): void
    {
        $previous = static function (): void {};
        pcntl_signal(SIGTERM, $previous);
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_async_signals(true);

        $dispatcher = new Dispatcher(new DummyDriver([new Message('job', [], 'a')]));
        $dispatcher->registerHandler('job', $this->handler(SIGTERM));
        $dispatcher->handle();

        self::assertSame($previous, pcntl_signal_get_handler(SIGTERM));
        self::assertSame(SIG_IGN, pcntl_signal_get_handler(SIGINT));
        self::assertTrue(pcntl_async_signals());
    }

    public function testHandlersAreRestoredWhenHandleThrows(): void
    {
        pcntl_signal(SIGTERM, SIG_IGN);
        pcntl_async_signals(false);
        $dispatcher = new Dispatcher(new class extends DummyDriver {
            #[\Override]
            public function wait(\Closure $callback, array $priorities = []): void
            {
                throw new \Error('driver blew up');
            }
        });

        $escaped = null;
        try {
            $dispatcher->handle();
        } catch (\Throwable $e) {
            $escaped = $e;
        }

        self::assertInstanceOf(\Error::class, $escaped, 'the Error must escape');
        self::assertSame('driver blew up', $escaped->getMessage());

        self::assertSame(SIG_IGN, pcntl_signal_get_handler(SIGTERM));
        self::assertFalse(pcntl_async_signals());
    }

    public function testHandlersAreInstalledOnlyWhileHandleRuns(): void
    {
        $dispatcher = new Dispatcher(new DummyDriver([new Message('job', [], 'a')]));
        $seen = new \ArrayObject();
        $dispatcher->registerHandler('job', new readonly class ($seen) implements HandlerInterface {
            /** @param \ArrayObject<int, array{0: string, 1: callable|int}> $seen */
            public function __construct(private \ArrayObject $seen) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                $this->seen[] = ['term', pcntl_signal_get_handler(SIGTERM)];
                $this->seen[] = ['int', pcntl_signal_get_handler(SIGINT)];
                $this->seen[] = ['async', (int) pcntl_async_signals()];

                return true;
            }
        });
        $dispatcher->handle();

        $this->handlerSeen = array_values($seen->getArrayCopy());
        self::assertCount(3, $this->handlerSeen);
        self::assertInstanceOf(\Closure::class, $this->handlerSeen[0][1]);
        self::assertInstanceOf(\Closure::class, $this->handlerSeen[1][1]);
        self::assertSame(['async', 0], $this->handlerSeen[2], 'signals are dispatched at safe points, not asynchronously');
        self::assertSame(SIG_DFL, pcntl_signal_get_handler(SIGTERM));
    }

    public function testFurtherSignalsDuringTheMessageDoNotKillTheWorker(): void
    {
        $logger = new TestLogger();
        $dispatcher = new Dispatcher(new DummyDriver([new Message('job', [], 'a'), new Message('job', [], 'b')]), $logger);
        $dispatcher->registerHandler('job', new readonly class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                posix_kill(posix_getpid(), SIGTERM);
                posix_kill(posix_getpid(), SIGINT);

                return true;
            }
        });
        $dispatcher->handle();

        $logs = $logger->getLogs();
        self::assertCount(3, $logs, 'only a is handled');
        self::assertSame('Exiting hermes dispatcher - received SIGTERM', (string) $logs[2]['message']);
    }

    public function testAnEarlierSignalDoesNotStopTheNextRun(): void
    {
        $driver = new DummyDriver([new Message('job', [], 'a'), new Message('job', [], 'b'), new Message('job', [], 'c')]);
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', $this->handler(SIGTERM, 'a'));
        $dispatcher->handle();
        self::assertNull(SignalStop::received(), 'the flag ends with the handle() call it stopped');

        $logger = new TestLogger();
        $next = new Dispatcher($driver, $logger);
        $next->registerHandler('job', $this->handler(null));
        $next->handle();

        self::assertNull(SignalStop::received());
        $ends = array_filter($logger->getLogs(), static fn(array $log): bool => str_starts_with((string) $log['message'], 'End handle'));
        self::assertCount(3, $ends, 'the second run handles every message the driver delivers');
    }

    public function testADriverUsedAfterASignalledHandleDoesNotStop(): void
    {
        $dispatcher = new Dispatcher(new DummyDriver([new Message('job', [], 'a')]));
        $dispatcher->registerHandler('job', $this->handler(SIGTERM));
        $dispatcher->handle();

        $driver = new class {
            use ShutdownTrait;

            public function stops(): bool
            {
                return $this->shouldShutdown();
            }
        };
        self::assertFalse($driver->stops(), 'the signal belonged to the handle() call that already returned');
    }

    public function testNothingIsDispatchedAfterHandleReturns(): void
    {
        new Dispatcher(new DummyDriver())->handle();
        $seen = new \ArrayObject();
        pcntl_signal(SIGUSR1, static function () use ($seen): void {
            $seen[] = 'usr1';
        });
        posix_kill(posix_getpid(), SIGUSR1);

        self::assertNull(SignalStop::received());
        self::assertSame([], $seen->getArrayCopy(), 'received() leaves other signals to their owner once handle() is done');
        pcntl_signal_dispatch();
        pcntl_signal(SIGUSR1, SIG_DFL);
        self::assertSame(['usr1'], $seen->getArrayCopy());
    }

    public function testIdleDriverStopsAtItsShutdownCheck(): void
    {
        // an idle driver loop that only polls and checks for shutdown, like the built-in drivers on an empty queue
        $driver = new class extends DummyDriver {
            use ShutdownTrait;

            #[\Override]
            public function wait(\Closure $callback, array $priorities = []): void
            {
                $deadline = microtime(true) + 10;
                while (microtime(true) < $deadline) {
                    $this->checkShutdown();
                    usleep(10_000);
                }
            }
        };
        $logger = new TestLogger();
        $sender = proc_open(['sh', '-c', 'sleep 0.3; kill -TERM ' . posix_getpid()], [], $pipes);
        self::assertIsResource($sender);
        $started = microtime(true);
        new Dispatcher($driver, $logger)->handle();
        proc_close($sender);

        self::assertLessThan(5.0, microtime(true) - $started);
        self::assertSame([['notice', 'Exiting hermes dispatcher - received SIGTERM']], array_map(
            static fn(array $log): array => [$log['level'], (string) $log['message']],
            $logger->getLogs(),
        ));
    }

    public function testDescribe(): void
    {
        self::assertSame('SIGTERM', SignalStop::describe(SIGTERM));
        self::assertSame('SIGINT', SignalStop::describe(SIGINT));
        self::assertSame('signal ' . SIGUSR1, SignalStop::describe(SIGUSR1));
    }

    private function assertSignalStops(int $signal, string $name): void
    {
        $logger = new TestLogger();
        $driver = new DummyDriver([new Message('job', [], 'a'), new Message('job', [], 'b')]);
        $dispatcher = new Dispatcher($driver, $logger);
        $handler = $this->handler($signal);
        $dispatcher->registerHandler('job', $handler);
        $dispatcher->handle();

        self::assertSame([
            ['info', 'Start handle message #a (job) priority:100'],
            ['info', 'End handle message #a (job)'],
            ['notice', "Exiting hermes dispatcher - received {$name}"],
        ], array_map(static fn(array $log): array => [$log['level'], (string) $log['message']], $logger->getLogs()));
    }

    private function handler(?int $signal, ?string $onlyFor = null): HandlerInterface
    {
        return new readonly class ($signal, $onlyFor) implements HandlerInterface {
            public function __construct(private ?int $signal, private ?string $onlyFor) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                if ($this->signal !== null && ($this->onlyFor === null || $this->onlyFor === $message->getId())) {
                    posix_kill(posix_getpid(), $this->signal);
                    usleep(1000); // the handler keeps running after the signal
                }

                return true;
            }
        };
    }
}
