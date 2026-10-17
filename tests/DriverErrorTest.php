<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Logger\TestLogger;

/**
 * @covers \Tomaj\Hermes\Dispatcher
 */
class DriverErrorTest extends TestCase
{
    public function testDriverExceptionIsLoggedAndRethrown(): void
    {
        $error = new RuntimeException('connection lost');
        $logger = new TestLogger();
        $dispatcher = new Dispatcher($this->failingDriver($error), $logger);

        try {
            $dispatcher->handle();
            self::fail('handle() must rethrow the driver exception');
        } catch (RuntimeException $caught) {
            self::assertSame($error, $caught);
        }

        $logs = $logger->getLogs();
        self::assertCount(1, $logs);
        self::assertSame('error', $logs[0]['level']);
        self::assertSame('Exiting hermes dispatcher - driver error: RuntimeException - connection lost', $logs[0]['message']);
        self::assertSame(['exception' => $error], $logs[0]['context']);
    }

    public function testDriverExceptionIsRethrownWithoutLogger(): void
    {
        $error = new RuntimeException('broken payload');
        $dispatcher = new Dispatcher($this->failingDriver($error));

        $this->expectExceptionObject($error);
        $dispatcher->handle();
    }

    private function failingDriver(RuntimeException $error): DriverInterface
    {
        return new readonly class ($error) implements DriverInterface {
            public function __construct(private RuntimeException $error) {}

            #[\Override]
            public function send(MessageInterface $message, int $priority = 100): bool
            {
                return true;
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                throw $this->error;
            }
        };
    }
}
