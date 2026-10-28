<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use Closure;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;

/**
 * Class DispatcherShutdownTest
 *
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class DispatcherShutdownTest extends TestCase
{
    public function testEmitWithDummyDriverNoShutdown(): void
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopShutdown = new StopShutdown();
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();

        // no shutdown; we received both messages
        self::assertCount(2, $receivedMessages);
    }

    public function testEmitWithDummyDriverWithShutdown(): void
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopShutdown = new StopShutdown();
        $stopShutdown->shutdown(new \DateTime());
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        self::assertCount(1, $receivedMessages);
    }

    public function testDriverWithShutdownTraitGetsTheResultAndStopsItself(): void
    {
        $driver = new class ([new Message('event1', ['a' => 'b']), new Message('event1', ['c' => 'd'])]) extends DummyDriver {
            use ShutdownTrait;

            /** @var array<int, mixed> */
            public array $results = [];

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                while (($message = $this->getMessage()) instanceof MessageInterface) {
                    $this->checkShutdown();
                    $this->results[] = $callback($message);
                }
            }
        };
        $stopShutdown = new StopShutdown();
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);
        $dispatcher->registerHandler('event1', new readonly class ($stopShutdown) implements HandlerInterface {
            public function __construct(private StopShutdown $shutdown) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                $this->shutdown->shutdown();

                return false;
            }
        });

        $dispatcher->handle();

        // the callback returned the result of the first message; the driver's own check stopped it
        self::assertSame([false], $driver->results);
    }

    public function testDriverWithItsOwnSetShutdownIsStoppedAfterTheMessage(): void
    {
        // setShutdown() without ShutdownTrait: the driver may never check, so the dispatcher stops it
        $driver = new class ([new Message('event1', ['a' => 'b']), new Message('event1', ['c' => 'd'])]) extends DummyDriver {
            public function setShutdown(ShutdownInterface $shutdown): void {}
        };
        $stopShutdown = new StopShutdown();
        $stopShutdown->shutdown();
        $handler = new TestHandler();
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);
        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        self::assertCount(1, $handler->getReceivedMessages());
    }
}
