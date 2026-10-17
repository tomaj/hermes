<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Predis\Response\Status;
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Test\Double\PredisClientDouble;

/**
 * Shutdowns receive the worker start time as an immutable DateTimeInterface (#108).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Driver\ShutdownTrait
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class ImmutableStartTimeTest extends TestCase
{
    public function testDispatcherPassesTheSameImmutableStartTimeAfterEveryMessage(): void
    {
        $shutdown = new class implements ShutdownInterface {
            /** @var list<DateTimeInterface> */
            public array $startTimes = [];

            #[\Override]
            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                $this->startTimes[] = $startTime;
                if ($startTime instanceof DateTime) {
                    // what a careless implementation could do to a mutable start time
                    $startTime->modify('-1 day');
                }

                return false;
            }

            #[\Override]
            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        };
        $driver = new class implements DriverInterface {
            #[\Override]
            public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
            {
                return true;
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                $callback(new Message('a', []));
                $callback(new Message('b', []));
            }
        };

        new Dispatcher($driver, null, $shutdown)->handle();

        self::assertCount(2, $shutdown->startTimes);
        self::assertInstanceOf(DateTimeImmutable::class, $shutdown->startTimes[0]);
        self::assertSame($shutdown->startTimes[0], $shutdown->startTimes[1]);
    }

    public function testShutdownTraitPassesAnImmutableStartTime(): void
    {
        $shutdown = new class implements ShutdownInterface {
            public ?DateTimeInterface $startTime = null;

            #[\Override]
            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                $this->startTime = $startTime;

                return false;
            }

            #[\Override]
            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        };
        $driver = new class {
            use ShutdownTrait;

            public function check(): void
            {
                $this->checkShutdown();
            }
        };

        $before = microtime(true);
        $driver->setShutdown($shutdown);
        $driver->check();

        self::assertInstanceOf(DateTimeImmutable::class, $shutdown->startTime);
        self::assertGreaterThanOrEqual($before, (float) $shutdown->startTime->format('U.u'));
    }

    public function testBuiltInShutdownsAcceptImmutableAndMutableTimes(): void
    {
        $file = sys_get_temp_dir() . '/hermes_immutable_' . bin2hex(random_bytes(4));
        $shutdown = new SharedFileShutdown($file);
        try {
            self::assertTrue($shutdown->shutdown(new DateTimeImmutable('@1700000000.5')));
            self::assertTrue($shutdown->shouldShutdown(new DateTimeImmutable('@1700000000.25')));
            self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000.75')));
            self::assertTrue($shutdown->shutdown(new DateTime('@1700000001.5')));
            self::assertSame('1700000001.500000', file_get_contents($file));
        } finally {
            @unlink($file);
        }

        $predis = $this->createMock(PredisClientDouble::class);
        $predis->expects(self::once())->method('set')->with('hermes_shutdown', '1700000000.500000')
            ->willReturn(new Status('OK'));
        $predis->method('get')->willReturn('1700000000.500000');
        $predisShutdown = new PredisShutdown($predis);
        self::assertTrue($predisShutdown->shutdown(new DateTimeImmutable('@1700000000.5')));
        self::assertTrue($predisShutdown->shouldShutdown(new DateTimeImmutable('@1700000000.25')));
    }

    /**
     * @requires extension redis
     */
    public function testRedisShutdownAcceptsImmutableTimes(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('set')->with('hermes_shutdown', '1700000000.500000')->willReturn(true);
        $redis->method('get')->willReturn('1700000000.500000');
        $shutdown = new RedisShutdown($redis);

        self::assertTrue($shutdown->shutdown(new DateTimeImmutable('@1700000000.5')));
        self::assertTrue($shutdown->shouldShutdown(new DateTimeImmutable('@1700000000.25')));
        self::assertFalse($shutdown->shouldShutdown(new DateTimeImmutable('@1700000000.75')));
    }
}
