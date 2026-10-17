<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Closure;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

/**
 * @covers \Tomaj\Hermes\Dispatcher
 */
class DispatcherConstructorTest extends TestCase
{
    public function testConstructorWithShutdownAndDriverWithSetShutdown(): void
    {
        // Create a driver that has setShutdown method
        $driver = new class implements DriverInterface {
            use ShutdownTrait;

            private bool $shutdownSet = false;

            #[\Override]
            public function send(MessageInterface $message, int $priority = 100): bool
            {
                return true;
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void
            {
                // no-op
            }

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                // no-op
            }

            public function setShutdown(ShutdownInterface $shutdown): void
            {
                $this->shutdownSet = true;
                $this->shutdown = $shutdown;
            }

            public function wasShutdownSet(): bool
            {
                return $this->shutdownSet;
            }
        };

        $shutdown = new class implements ShutdownInterface {
            #[\Override]
            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                return false;
            }

            #[\Override]
            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        };

        new Dispatcher($driver, null, $shutdown);

        // Verify that setShutdown was called on the driver
        self::assertTrue($driver->wasShutdownSet());
    }
}
