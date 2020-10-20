<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Dispatcher;

/**
 * Class LoggerTest
 * @package Tomaj\Hermes\Test\Logger
 * @covers Emitter
 */
class LoggerTest extends TestCase
{
    public function testLoggerWithEmit()
    {
        $driver = new DummyDriver();
        $testLogger = new TestLogger();
        $emitter = new Emitter($driver, $testLogger);
        $message = new Message('test', ['asdsd' => 'asdsd']);
        $emitter->emit($message);

        $logData = $testLogger->getLogs();
        $this->assertCount(1, $logData);

        $log = $logData[0];
        $this->assertEquals('test', $log['context']['type']);
        $this->assertEquals(['asdsd' => 'asdsd'], $log['context']['payload']);
        $this->assertStringContainsString($message->getId(), $log['message']);
    }

    public function testHandlerLogger()
    {
        $message1 = new Message('event1', ['a' => 'b']);

        $driver = new DummyDriver([$message1]);
        $testLogger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $testLogger);

        $handler = new TestHandler();
        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $logs = $testLogger->getLogs();
        $this->assertCount(2, $logs);

        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals("Start handle message #{$message1->getId()} ({$message1->getType()})", $logs[0]['message']);

        $this->assertEquals('info', $logs[1]['level']);
        $this->assertEquals("End handle message #{$message1->getId()} ({$message1->getType()})", $logs[1]['message']);
    }
}
