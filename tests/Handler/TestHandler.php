<?php

namespace Tomaj\Hermes\Test\Handler;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Handler\HandlerInterface;

class TestHandler implements HandlerInterface
{
    private $receivedMessages = [];

    private $result;

    public function __construct($result = true)
    {
        $this->result = $result;
    }

    public function handle(MessageInterface $message): bool
    {
        $this->receivedMessages[] = $message;
        return $this->result;
    }

    public function getReceivedMessages(): array
    {
        return $this->receivedMessages;
    }
}
