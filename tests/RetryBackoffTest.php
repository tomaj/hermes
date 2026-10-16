<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Closure;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;

#[CoversClass(Dispatcher::class)]
class RetryBackoffTest extends TestCase
{
    /**
     * Runs one failing message through a dispatcher and returns the retry copy plus the time window of the run.
     *
     * @return array{MessageInterface, float, float}
     */
    private function retryOf(int $retries, ?Randomizer $randomizer): array
    {
        $driver = new class (new Message('job', ['a' => 1], 'id-1', 1000.0, null, $retries)) implements DriverInterface {
            /** @var list<MessageInterface> */
            public array $sent = [];

            public function __construct(private readonly MessageInterface $message) {}

            #[\Override]

            public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
            {
                $this->sent[] = $message;
                return true;
            }

            #[\Override]

            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]

            public function wait(Closure $callback, array $priorities = []): void
            {
                $callback($this->message, Dispatcher::DEFAULT_PRIORITY);
            }
        };

        $dispatcher = new Dispatcher($driver, null, null, $randomizer);
        $dispatcher->registerHandler('job', new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]

            public function handle(MessageInterface $message): bool
            {
                throw new Exception('failed');
            }
        });

        $before = microtime(true);
        $dispatcher->handle();
        $after = microtime(true);

        self::assertCount(1, $driver->sent);
        return [$driver->sent[0], $before, $after];
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function retries(): iterable
    {
        foreach ([0, 1, 2, 3, 7] as $retries) {
            foreach ([1, 42, 2026] as $seed) {
                yield "retries {$retries}, seed {$seed}" => [$retries, $seed];
            }
        }
    }

    #[DataProvider('retries')]
    public function testSeededRandomizerGivesExactDelay(int $retries, int $seed): void
    {
        [$retry, $before, $after] = $this->retryOf($retries, new Randomizer(new Mt19937($seed)));

        $jitter = new Randomizer(new Mt19937($seed))->getInt(1, 30);
        $delay = $retries ** 4 + 15 + $jitter * ($retries + 1);

        $executeAt = $retry->getExecuteAt();
        self::assertNotNull($executeAt);
        self::assertGreaterThanOrEqual($before + $delay, $executeAt);
        self::assertLessThanOrEqual($after + $delay, $executeAt);

        self::assertSame($retries + 1, $retry->getRetries());
        self::assertSame('id-1', $retry->getId());
        self::assertSame(1000.0, $retry->getCreated());
        self::assertSame(['a' => 1], $retry->getPayload());
    }

    public function testDefaultRandomizerKeepsJitterBetweenOneAndThirtySeconds(): void
    {
        $min = PHP_INT_MAX;
        $max = PHP_INT_MIN;
        for ($i = 0; $i < 600; $i++) {
            [$retry, $before, $after] = $this->retryOf(0, null);
            $executeAt = $retry->getExecuteAt();
            self::assertNotNull($executeAt);
            // first retry: 0 ** 4 + 15 + jitter * 1, jitter in [1, 30]
            $delay = (int) round($executeAt - ($before + $after) / 2);
            $min = min($min, $delay);
            $max = max($max, $delay);
        }

        self::assertSame(16, $min);
        self::assertSame(45, $max);
    }
}
