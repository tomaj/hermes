<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Closure;
use Error;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Logger\TestLogger;
use TypeError;

/**
 * A PHP Error from a handler is handled like an exception (#112).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class HandlerErrorTest extends TestCase
{
    public function testErrorIsLoggedRetriedAndLaterHandlersAndMessagesStillRun(): void
    {
        $driver = new class implements DriverInterface {
            /** @var list<MessageInterface> */
            public array $sent = [];

            /** @var list<mixed> */
            public array $results = [];

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
                $this->results[] = $callback(new Message('job', [], 'first'));
                $this->results[] = $callback(new Message('job', [], 'second'));
            }
        };
        $failing = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                if ($message->getId() === 'first') {
                    throw new TypeError('Argument #1 must be of type int, string given');
                }

                return true;
            }
        };
        $later = new class implements HandlerInterface {
            /** @var list<string> */
            public array $ids = [];

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                $this->ids[] = $message->getId();

                return true;
            }
        };
        $logger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $logger);
        $dispatcher->registerHandlers('job', [$failing, $later]);

        $dispatcher->handle();

        self::assertSame([false, true], $driver->results);
        self::assertSame(['first', 'second'], $later->ids);
        self::assertCount(1, $driver->sent);
        self::assertSame('first', $driver->sent[0]->getId());
        self::assertSame(1, $driver->sent[0]->getRetries());

        $errors = array_values(array_filter($logger->getLogs(), static fn(array $log): bool => $log['level'] === LogLevel::ERROR));
        self::assertCount(1, $errors);
        self::assertSame('Handler ' . $failing::class . ' throws exception - Argument #1 must be of type int, string given', $errors[0]['message']);
        self::assertInstanceOf(Error::class, $errors[0]['context']['exception']);
    }

    public function testFailedRetrySendIsLoggedAndRethrown(): void
    {
        $driver = new class implements DriverInterface {
            #[\Override]
            public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
            {
                throw new RuntimeException('connection lost');
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                $callback(new Message('job', [], 'first'));
            }
        };
        $failing = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new TypeError('handler failed');
            }
        };
        $logger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $logger);
        $dispatcher->registerHandler('job', $failing);

        try {
            $dispatcher->handle();
            self::fail('the send error must leave handle()');
        } catch (RuntimeException $e) {
            self::assertSame('connection lost', $e->getMessage());
        }

        $errors = array_values(array_filter($logger->getLogs(), static fn(array $log): bool => $log['level'] === LogLevel::ERROR));
        self::assertCount(3, $errors);
        self::assertSame('Handler ' . $failing::class . ' throws exception - handler failed', $errors[0]['message']);
        self::assertSame('Retry of message #first (job) lost - driver send failed: RuntimeException - connection lost', $errors[1]['message']);
        self::assertSame('Exiting hermes dispatcher - driver error: RuntimeException - connection lost', $errors[2]['message']);
    }
}
