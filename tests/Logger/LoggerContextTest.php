<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;

/**
 * The context arrays that Emitter and Dispatcher pass to the PSR-3 logger.
 *
 * @covers \Tomaj\Hermes\Emitter
 * @covers \Tomaj\Hermes\Dispatcher
 */
class LoggerContextTest extends TestCase
{
    public function testEmitterLogsTheMessageContext(): void
    {
        $logger = new TestLogger();
        $message = new Message('type', ['a' => 'b'], 'id-1', 1700000000.5, 1700000100.25, 3);

        new Emitter(new DummyDriver(), $logger)->emit($message);

        self::assertSame(
            [
                'id' => 'id-1',
                'created' => 1700000000.5,
                'type' => 'type',
                'payload' => ['a' => 'b'],
                'retries' => 3,
                'execute_at' => 1700000100.25,
            ],
            $logger->getLogs()[0]['context'],
        );
    }

    public function testDispatcherLogsTheFullMessageContext(): void
    {
        $logger = new TestLogger();
        $message = new Message('type', ['a' => 'b'], 'id-1', 1700000000.5, 1700000100.25, 3);
        $dispatcher = new Dispatcher(new DummyDriver([$message]), $logger);
        $dispatcher->registerHandler('type', new TestHandler());

        $dispatcher->handle();

        $expected = [
            'id' => 'id-1',
            'created' => 1700000000.5,
            'type' => 'type',
            'payload' => ['a' => 'b'],
            'retries' => 3,
            'execute_at' => 1700000100.25,
        ];
        $contexts = array_column($logger->getLogs(), 'context');
        self::assertNotSame([], $contexts);
        foreach ($contexts as $context) {
            self::assertSame($expected, $context);
        }
    }

    public function testHandlerExceptionContextHoldsTheExceptionAndTheMessage(): void
    {
        $logger = new TestLogger();
        $exception = new RuntimeException('boom');
        $dispatcher = new Dispatcher(new DummyDriver([new Message('type', [], 'id-1', 1700000000.0)]), $logger);
        $dispatcher->registerHandler('type', new readonly class ($exception) implements HandlerInterface {
            public function __construct(private RuntimeException $exception) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw $this->exception;
            }
        });

        $dispatcher->handle();

        $errors = array_values(array_filter($logger->getLogs(), static fn(array $log): bool => $log['level'] === 'error'));
        self::assertCount(1, $errors);
        self::assertSame(
            [
                'error' => $exception,
                'message' => [
                    'id' => 'id-1',
                    'created' => 1700000000.0,
                    'type' => 'type',
                    'payload' => [],
                    'retries' => 0,
                    'execute_at' => null,
                ],
                'exception' => $exception,
            ],
            $errors[0]['context'],
        );
    }
}
