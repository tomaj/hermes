<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Driver\DummyDriver;

require dirname(__FILE__). '/../vendor/autoload.php';
require_once __DIR__ . '/DummyDriver.php';

class EmitTest extends PHPUnit_Framework_TestCase
{
    public function testEmitWithDummyDriver()
    {
        $driver = new DummyDriver();
        $dispatcher = new Dispatcher($driver);

        $this->assertNull($driver->getMessage());

        $dispatcher->emit(new Message('event-type', 'content'));

        $message = $driver->getMessage();
        $this->assertEquals('event-type', $message->getType());
        $this->assertEquals('content', $message->getPayload());
    }
}
