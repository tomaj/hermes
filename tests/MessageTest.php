<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;

require dirname(__FILE__). '/../vendor/autoload.php';

class MessageTest extends PHPUnit_Framework_TestCase
{
    public function testSimpleMessage()
    {
        $message = new Message('new-user', ['hello' => 'kitty']);
        $this->assertEquals('new-user', $message->getType());
        $this->assertEquals(['hello' => 'kitty'], $message->getPayload());
    }

    public function testMessageWithoutPayload()
    {
        $message = new Message('asdssd');
        $this->assertEquals('asdssd', $message->getType());
        $this->assertEquals(null, $message->getPayload());
    }
}
