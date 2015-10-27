<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Handler\EchoHandler;

require __DIR__ . '/../../vendor/autoload.php';

class EchoHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testEchoHandler()
    {
    	$message = new Message('message1key', ['a' => 'b']);
    	$output = "Received message: #{$message->getId()} (type message1key)\n";
    	$output .= "Payload: {\"a\":\"b\"}\n";
    	$this->expectOutputString($output);
		
        $echoHandler = new EchoHandler();
        $echoHandler->handle($message);
    }
}
