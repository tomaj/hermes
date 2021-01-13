<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Handler\HandlerInterface;

class TestHandler implements HandlerInterface
{
    /** @var MessageInterface[] */
    private $receivedMessages = [];

    /** @var bool  */
    private $result;

    public function __construct(bool $result = true)
    {
        $this->result = $result;
    }

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
