<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Test\Handler\ExceptionHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;

class HandleTest extends TestCase
{
    public function testEmitWithDummyDriver()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event2', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $dispatcher = new Dispatcher($driver);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event2', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
        $this->assertEquals('event2', $receivedMessages[0]->getType());
        $this->assertEquals(['c' => 'd'], $receivedMessages[0]->getPayload());
        $this->assertTrue($driver->waitResult());
    }

    public function testMuplitpleHandlersOnOneEvent()
    {
        $message1 = new Message('eventx', ['a' => 'x']);

        $driver = new DummyDriver([$message1]);
        $dispatcher = new Dispatcher($driver);

        $handler1 = new TestHandler();
        $handler2 = new TestHandler(false);

        $dispatcher->registerHandler('eventx', $handler1);
        $dispatcher->registerHandler('eventx', $handler2);

        $dispatcher->handle();

        $receivedMessages = $handler1->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
        $this->assertEquals('eventx', $receivedMessages[0]->getType());
        $this->assertEquals(['a' => 'x'], $receivedMessages[0]->getPayload());

        $receivedMessages = $handler2->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
        $this->assertEquals('eventx', $receivedMessages[0]->getType());
        $this->assertEquals(['a' => 'x'], $receivedMessages[0]->getPayload());

        $this->assertFalse($driver->waitResult());
    }

    public function testOtherEvent()
    {
        $message1 = new Message('eventx', ['a' => 'x']);
        $message2 = new Message('eventy', ['a' => 'x']);

        $driver = new DummyDriver([$message1, $message2]);
        $dispatcher = new Dispatcher($driver);

        $handler = new TestHandler();

        $dispatcher->registerHandler('unknown', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertEquals(0, count($receivedMessages));
        $this->assertTrue($driver->waitResult());
    }

    public function testHandlerWithException()
    {
        $message1 = new Message('eventx', ['a' => 'x']);

        $driver = new DummyDriver([$message1]);
        $dispatcher = new Dispatcher($driver);

        $dispatcher->registerHandler('eventx', new ExceptionHandler());
        $dispatcher->handle();

        $this->assertFalse($driver->waitResult());
    }
}
