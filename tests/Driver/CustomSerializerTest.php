<?php

namespace Tomaj\Hermes\Test;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Driver\DummySerializer;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Message;

class CustomSerializerTest extends PHPUnit_Framework_TestCase
{
    public function testCustomSerializerTest()
    {
        $message = new Message('eventx', ['a' => 'x']);
        $dummyDriver = new DummyDriver();
        $dummyDriver->setSerializer(new DummySerializer());
        $dummyDriver->send($message);

        $receivedMessage = false;
        $dummyDriver->wait(function (MessageInterface $message) use (&$receivedMessage) {
            $receivedMessage = $message;
        });
        $this->assertEquals($message, $receivedMessage);
    }
}
