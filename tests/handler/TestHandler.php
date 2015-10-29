<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class TestHandler implements HandlerInterface
{
    private $receivedMessages = [];

    private $result;

    public function __construct($result = true)
    {
    	$this->result = $result;
    }

    public function handle(MessageInterface $message)
    {
        $this->receivedMessages[] = $message;
        return $this->result;
    }

    public function getReceivedMessages()
    {
        return $this->receivedMessages;
    }
}
