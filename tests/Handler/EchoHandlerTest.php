<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Handler\EchoHandler;

/**
 * Class EchoHandlerTest
 *
 * @package Tomaj\Hermes\Test\Handler
 * @covers \Tomaj\Hermes\Handler\EchoHandler
 * @covers \Tomaj\Hermes\Message
 */
class EchoHandlerTest extends TestCase
{
    public function testEchoHandler(): void
    {
        $message = new Message('message1key', ['a' => 'b']);
        $output = "Received message: #{$message->getId()} (type message1key)\n";
        $output .= "Payload: {\"a\":\"b\"}\n";
        $this->expectOutputString($output);
        
        $echoHandler = new EchoHandler();
        $echoHandler->handle($message);
    }
}
