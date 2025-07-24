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
 * @covers \Tomaj\Hermes\Handler\RetryTrait
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

    public function testEmitMethod(): void
    {
        $driver = new DummyDriver([]);
        $driver->setupPriorityQueue('high', 200);
        $dispatcher = new Dispatcher($driver);

        $message = new Message('test-event', ['test' => 'data']);
        $result = $dispatcher->emit($message, 200);

        $this->assertSame($dispatcher, $result);
        
        // Check that the message was sent via the driver
        $sentMessage = $driver->getMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals('test-event', $sentMessage->getType());
        $this->assertEquals(['test' => 'data'], $sentMessage->getPayload());
    }

    public function testRegisterHandlers(): void
    {
        $handler1 = new TestHandler();
        $handler2 = new TestHandler();
        $handlers = [$handler1, $handler2];

        $message = new Message('test-event', ['test' => 'data']);
        $driver = new DummyDriver([$message]);
        $dispatcher = new Dispatcher($driver);

        $result = $dispatcher->registerHandlers('test-event', $handlers);

        $this->assertSame($dispatcher, $result);

        // Test that both handlers receive the message
        $dispatcher->handle();

        $this->assertCount(1, $handler1->getReceivedMessages());
        $this->assertCount(1, $handler2->getReceivedMessages());
    }

    public function testRetryMessage(): void
    {
        // Create a handler that uses RetryTrait
        $handler = new class implements \Tomaj\Hermes\Handler\HandlerInterface {
            use \Tomaj\Hermes\Handler\RetryTrait;
            
            public function handle(\Tomaj\Hermes\MessageInterface $message): bool
            {
                // Throw exception to trigger retry
                throw new \Exception('Test exception to trigger retry');
            }
        };

        $message = new Message('retry-event', ['test' => 'retry'], 'test-id', microtime(true), null, 5);
        $driver = new DummyDriver([$message]);
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('retry-event', $handler);

        $dispatcher->handle();

        // Should have sent a retry message since retries (5) < maxRetry (25)
        // Need to check both messages that might be in the queue
        $message1 = $driver->getMessage();
        $message2 = $driver->getMessage();
        
        // One should be the retry message with retries=6
        $foundRetryMessage = false;
        if ($message1 && $message1->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        if ($message2 && $message2->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        
        $this->assertTrue($foundRetryMessage, 'Should have found a retry message with retries=6');
    }

    public function testRetryMessageMaxRetriesReached(): void
    {
        // Create a handler that uses RetryTrait with custom maxRetry
        $handler = new class implements \Tomaj\Hermes\Handler\HandlerInterface {
            use \Tomaj\Hermes\Handler\RetryTrait;
            
            public function maxRetry(): int
            {
                return 5; // Lower max retry for this test
            }
            
            public function handle(\Tomaj\Hermes\MessageInterface $message): bool
            {
                // Throw exception to trigger retry check
                throw new \Exception('Test exception to trigger retry');
            }
        };

        $message = new Message('retry-event', ['test' => 'retry'], 'test-id', microtime(true), null, 5);
        $driver = new DummyDriver([$message]);
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('retry-event', $handler);

        $dispatcher->handle();

        // Should not have sent a retry message since retries (5) >= maxRetry (5)
        // But the original message should still be there
        $message1 = $driver->getMessage();
        $message2 = $driver->getMessage();
        
        // Should not find any message with retries=6 (no retry should have been sent)
        $foundRetryMessage = false;
        if ($message1 && $message1->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        if ($message2 && $message2->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        
        $this->assertFalse($foundRetryMessage, 'Should not have found a retry message with retries=6');
    }

    public function testHandlerWithoutRetryMethods(): void
    {
        // Create a handler that doesn't use RetryTrait
        $handler = new class implements \Tomaj\Hermes\Handler\HandlerInterface {
            public function handle(\Tomaj\Hermes\MessageInterface $message): bool
            {
                // Throw exception but handler doesn't have retry methods
                throw new \Exception('Exception in handler without retry methods');
            }
        };

        $message = new Message('no-retry-event', ['test' => 'no-retry'], 'test-id', microtime(true), null, 5);
        $driver = new DummyDriver([$message]);
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('no-retry-event', $handler);

        $dispatcher->handle();

        // Should not have sent a retry message since handler doesn't have retry methods
        // But the original message should still be there
        $message1 = $driver->getMessage();
        $message2 = $driver->getMessage();
        
        // Should not find any message with retries=6 (no retry should have been sent)
        $foundRetryMessage = false;
        if ($message1 && $message1->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        if ($message2 && $message2->getRetries() === 6) {
            $foundRetryMessage = true;
        }
        
        $this->assertFalse($foundRetryMessage, 'Should not have found a retry message with retries=6');
    }
}
