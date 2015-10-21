<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Driver\DummyDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Handler\TestHandler;

require dirname(__FILE__). '/../vendor/autoload.php';
require_once __DIR__ . '/DummyDriver.php';
require_once __DIR__ . '/TestLogger.php';
require_once __DIR__ . '/TestHandler.php';

class LoggerTest extends PHPUnit_Framework_TestCase
{
    public function testLoggerWithEmit()
    {
        $driver = new DummyDriver();
        $testLogger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $testLogger);
        $message = new Message('test', ['asdsd' => 'asdsd']);
        $dispatcher->emit($message);

        $logData = $testLogger->getLogs();
        $this->assertCount(1, $logData);

        $log = $logData[0];
        $this->assertEquals($log['context']['type'], 'test');
        $this->assertEquals($log['context']['payload'], ['asdsd' => 'asdsd']);
        $this->assertContains($message->getId(), $log['message']);
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
