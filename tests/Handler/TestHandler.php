<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class TestHandler implements HandlerInterface
{
    /** @var MessageInterface[] */
    private array $receivedMessages = [];

    public function __construct(private readonly bool $result = true) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $this->receivedMessages[] = $message;
        return $this->result;
    }

    /**
     * @return MessageInterface[]
     */
    public function getReceivedMessages(): array
    {
        return $this->receivedMessages;
    }
}
