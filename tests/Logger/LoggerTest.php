<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
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
}
