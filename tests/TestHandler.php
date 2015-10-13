<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class TestHandler implements HandlerInterface
{
    private $receivedMessages = [];

    public function handle(MessageInterface $message)
    {
        $this->receivedMessages[] = $message;
    }

    public function getReceivedMessages()
    {
        return $this->receivedMessages;
    }
}
