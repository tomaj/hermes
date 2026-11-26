<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;

/**
 * A retry copy goes back to the priority the message was received with (#183).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class RetryPriorityTest extends TestCase
{
    public function testRetryIsSentWithThePriorityTheMessageWasReceivedWith(): void
    {
        $driver = new class implements DriverInterface {
            /** @var list<array{string, int}> */
            public array $sent = [];

            #[\Override]
            public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
            {
                $this->sent[] = [$message->getId(), $priority];

                return true;
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                $callback(new Message('job', [], 'high'), 200);
                $callback(new Message('job', [], 'low'), 50);
                // drivers without priorities (RabbitMQ, SQS, Kafka) call the callback with the message only
                $callback(new Message('job', [], 'none'));
            }
        };
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', $this->failingHandler());

        $dispatcher->handle();

        self::assertSame([['high', 200], ['low', 50], ['none', Dispatcher::DEFAULT_PRIORITY]], $driver->sent);
    }

    private function failingHandler(): HandlerInterface
    {
        return new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('temporary failure');
            }
        };
    }
}
