<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Ramsey\Uuid\Uuid;

class Message implements MessageInterface
{
    private readonly string $messageId;

    private readonly float $created;

    /**
     * Native implementation of message.
     *
     * @param array<mixed>|null $payload
     * @param float|null $created timestamp (microtime(true))
     * @param float|null $executeAt timestamp (microtime(true))
     */
    public function __construct(
        private readonly string $type,
        private readonly ?array $payload = null,
        ?string $messageId = null,
        ?float $created = null,
        private readonly ?float $executeAt = null,
        private readonly int $retries = 0,
    ) {
        $this->messageId = ($messageId === null || $messageId === '')
            ? Uuid::uuid4()->toString()
            : $messageId;

        $this->created = $created ?? microtime(true);
    }

    public function getId(): string
    {
        return $this->messageId;
    }

    public function getCreated(): float
    {
        return $this->created;
    }

    public function getExecuteAt(): ?float
    {
        return $this->executeAt;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }
}
