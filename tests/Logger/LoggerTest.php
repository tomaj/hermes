<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;

/**
 * Class LoggerTest
 *
 * @package Tomaj\Hermes\Test\Logger
 * @covers \Tomaj\Hermes\Emitter
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class LoggerTest extends TestCase
{
    public function testLoggerWithEmit(): void
    {
        $driver = new DummyDriver();
        $testLogger = new TestLogger();
        $emitter = new Emitter($driver, $testLogger);
        $message = new Message('test', ['asdsd' => 'asdsd']);
        $emitter->emit($message);

        $logData = $testLogger->getLogs();
        self::assertCount(1, $logData);

        $log = $logData[0];
        self::assertEquals('test', $log['context']['type']);
        self::assertEquals(['asdsd' => 'asdsd'], $log['context']['payload']);
        self::assertStringContainsString($message->getId(), (string) $log['message']);
    }

    public function testHandlerLogger(): void
    {
        $message1 = new Message('event1', ['a' => 'b']);

        $driver = new DummyDriver([$message1]);
        $testLogger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $testLogger);

        $handler = new TestHandler();
        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $logs = $testLogger->getLogs();
        self::assertCount(2, $logs);

        $priority = Dispatcher::DEFAULT_PRIORITY;
        self::assertEquals('info', $logs[0]['level']);
        self::assertEquals("Start handle message #{$message1->getId()} ({$message1->getType()}) priority:{$priority}", $logs[0]['message']);

        self::assertEquals('info', $logs[1]['level']);
        self::assertEquals("End handle message #{$message1->getId()} ({$message1->getType()})", $logs[1]['message']);
    }

    public function testEmitterLogsDriverClassName(): void
    {
        $testLogger = new TestLogger();
        new Emitter(new DummyDriver(), $testLogger)->emit(new Message('test', [], 'abc'));

        self::assertSame('Dispatcher send message #abc to driver ' . DummyDriver::class, $testLogger->getLogs()[0]['message']);
    }

    public function testDeprecatedDispatcherEmitLogsDriverClassName(): void
    {
        $testLogger = new TestLogger();
        new Dispatcher(new DummyDriver(), $testLogger)->emit(new Message('test', [], 'abc'));

        self::assertSame(
            'Dispatcher send message #abc with priority 100 to driver ' . DummyDriver::class,
            $testLogger->getLogs()[0]['message'],
        );
    }

    public function testHandlerExceptionLogsHandlerClassName(): void
    {
        $testLogger = new TestLogger();
        $dispatcher = new Dispatcher(new DummyDriver([new Message('event1', [], 'abc')]), $testLogger);
        $handler = new class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('boom');
            }
        };
        $dispatcher->registerHandler('event1', $handler);
        $dispatcher->handle();

        $messages = array_map(static fn(array $log): string => (string) $log['message'], $testLogger->getLogs());
        self::assertContains('Handler ' . $handler::class . ' throws exception - boom', $messages);
    }

    public function testShutdownFromDriverIsLoggedAsNotice(): void
    {
        $testLogger = new TestLogger();
        new Dispatcher($this->driverThatThrows(new ShutdownException()), $testLogger)->handle();

        self::assertSame([['level' => 'notice', 'message' => 'Exiting hermes dispatcher - shutdown']], array_map(
            static fn(array $log): array => ['level' => $log['level'], 'message' => $log['message']],
            $testLogger->getLogs(),
        ));
    }

    public function testOtherDriverExceptionDoesNotLeaveHandle(): void
    {
        $testLogger = new TestLogger();
        new Dispatcher($this->driverThatThrows(new RuntimeException('broker down')), $testLogger)->handle();

        self::assertSame([], $testLogger->getLogs());
    }

    private function driverThatThrows(\Throwable $exception): DriverInterface
    {
        $driver = self::createStub(DriverInterface::class);
        $driver->method('wait')->willThrowException($exception);
        return $driver;
    }
}
