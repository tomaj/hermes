<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Restart;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;

/**
 * Class DispatcherRestartTest
 * @package Tomaj\Hermes\Test\Restart
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class DispatcherRestartTest extends TestCase
{
    public function testEmitWithDummyDriverNoRestart()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopRestart = new StopRestart();
        $dispatcher = new Dispatcher($driver, null, $stopRestart);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        // no restart; we received both messages
        $this->assertEquals(2, count($receivedMessages));
    }

    public function testEmitWithDummyDriverWithRestart()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopRestart = new StopRestart();
        $stopRestart->restart(new \DateTime());
        $dispatcher = new Dispatcher($driver, null, $stopRestart);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
    }
}
