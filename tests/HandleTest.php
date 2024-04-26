<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Test\Handler\ExceptionHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;

/**
 * Class HandleTest
 *
 * @package Tomaj\Hermes\Test
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Emitter
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class HandleTest extends TestCase
{
    public function testEmitWithDummyDriver(): void
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

    public function testMultipleHandlersOnOneEvent(): void
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

    public function testOtherEvent(): void
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

    public function testHandlerWithException(): void
    {
        $message1 = new Message('eventx', ['a' => 'x']);

        $driver = new DummyDriver([$message1]);
        $dispatcher = new Dispatcher($driver);

        $dispatcher->registerHandler('eventx', new ExceptionHandler());
        $dispatcher->handle();

        $this->assertFalse($driver->waitResult());
    }

    public function testPriorityProcessing(): void
    {
        $message1 = new Message('event1', ['n' => 1]);
        $message2 = new Message('event1', ['n' => 2]);
        $message3 = new Message('event1', ['n' => 3]);
        $message4 = new Message('event1', ['n' => 4]);

        $driver = new DummyDriver();
        $driver->setupPriorityQueue('high', Dispatcher::DEFAULT_PRIORITY + 10);

        $emitter = new Emitter($driver);
        $dispatcher = new Dispatcher($driver);

        $testHandler = new TestHandler();

        $dispatcher->registerHandler('event1', $testHandler);

        $emitter->emit($message1, Dispatcher::DEFAULT_PRIORITY);
        $emitter->emit($message2, Dispatcher::DEFAULT_PRIORITY + 10);
        $emitter->emit($message3, Dispatcher::DEFAULT_PRIORITY);
        $emitter->emit($message4, Dispatcher::DEFAULT_PRIORITY + 10);

        $dispatcher->handle();

        $receivedMessages = $testHandler->getReceivedMessages();
        $this->assertCount(4, $receivedMessages);
        $this->assertEquals(['n' => 2], $receivedMessages[0]->getPayload());
        $this->assertEquals(['n' => 4], $receivedMessages[1]->getPayload());
        $this->assertEquals(['n' => 1], $receivedMessages[2]->getPayload());
        $this->assertEquals(['n' => 3], $receivedMessages[3]->getPayload());
    }

    public function testMaxItemProcess(): void
    {
        $message1 = new Message('event1', ['n' => 1]);
        $message2 = new Message('event1', ['n' => 2]);
        $message3 = new Message('event1', ['n' => 3]);

        $driver = new DummyDriver();
        $driver->setMaxProcessItems(2);
        $driver->setupPriorityQueue('high', Dispatcher::DEFAULT_PRIORITY + 10);

        $emitter = new Emitter($driver);
        $dispatcher = new Dispatcher($driver);

        $testHandler = new TestHandler();

        $dispatcher->registerHandler('event1', $testHandler);

        $emitter->emit($message1, Dispatcher::DEFAULT_PRIORITY);
        $emitter->emit($message2, Dispatcher::DEFAULT_PRIORITY + 10);
        $emitter->emit($message3, Dispatcher::DEFAULT_PRIORITY);

        $dispatcher->handle();

        $receivedMessages = $testHandler->getReceivedMessages();
        $this->assertCount(2, $receivedMessages);
        $this->assertEquals(['n' => 2], $receivedMessages[0]->getPayload());
        $this->assertEquals(['n' => 1], $receivedMessages[1]->getPayload());
    }

    public function testUnregisterAllHandlers(): void
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event2', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $dispatcher = new Dispatcher($driver);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event2', $handler);
        $dispatcher->handle();

        $dispatcher->unregisterAllHandlers();
        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertCount(1, $receivedMessages);
    }

    public function testUnregisterHandler(): void
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event2', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $dispatcher = new Dispatcher($driver);

        $firstHandler = new TestHandler();
        $secondHandler = new TestHandler();

        $dispatcher->registerHandler('event1', $firstHandler);
        $dispatcher->registerHandler('event2', $secondHandler);
        $dispatcher->handle();

        $dispatcher->unregisterHandler('event2', $secondHandler);
        $dispatcher->handle();

        $firstHandlerReceivedMessages = $firstHandler->getReceivedMessages();
        $this->assertCount(2, $firstHandlerReceivedMessages);

        $secondHandlerReceivedMessages = $secondHandler->getReceivedMessages();
        $this->assertCount(1, $secondHandlerReceivedMessages);
    }
}
