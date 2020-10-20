<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Message;

/**
 * Class CustomSerializerTest
 * @package Tomaj\Hermes\Test\Driver
 * @covers Message
 */
class CustomSerializerTest extends TestCase
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
