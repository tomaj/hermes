<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Driver\DummyDriver;
use Tomaj\Hermes\Driver\DummySerializer;
use Tomaj\Hermes\MessageInterface;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/DummySerializer.php';

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
