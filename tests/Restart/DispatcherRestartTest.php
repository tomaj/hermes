<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Test\Restart\StopRestart;

class HandleRestartTest extends PHPUnit_Framework_TestCase
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
