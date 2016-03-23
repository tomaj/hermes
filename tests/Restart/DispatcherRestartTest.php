<?php

namespace Tomaj\Hermes\Test;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Test\Restart\StopRestart;

class HandleRestartTest extends PHPUnit_Framework_TestCase
{
    public function testEmitWithDummyDriver()
    {
        $message1 = new Message('event1', ['a' => 'b']);
        $message2 = new Message('event1', ['c' => 'd']);

        $driver = new DummyDriver([$message1, $message2]);
        $stopRestart = new StopRestart(1);
        $dispatcher = new Dispatcher($driver, null, $stopRestart);

        $handler = new TestHandler();

        $dispatcher->registerHandler('event1', $handler);

        $dispatcher->handle();

        $receivedMessages = $handler->getReceivedMessages();
        $this->assertEquals(1, count($receivedMessages));
    }
}
