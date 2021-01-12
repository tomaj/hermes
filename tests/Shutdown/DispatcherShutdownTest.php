<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;

/**
 * Class DispatcherShutdownTest
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class DispatcherShutdownTest extends TestCase
{
    public function testEmitWithDummyDriverNoShutdown()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopShutdown = new StopShutdown();
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();

        // no shutdown; we received both messages
        $this->assertEquals(2, count($receivedMessages));
    }

    public function testEmitWithDummyDriverWithShutdown()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopShutdown = new StopShutdown();
        $stopShutdown->shutdown(new \DateTime());
        $dispatcher = new Dispatcher($driver, null, $stopShutdown);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
    }
}
