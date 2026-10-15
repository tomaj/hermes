<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Ramsey\Uuid\Uuid;

final readonly class Message implements MessageInterface
{
    private string $messageId;

    private float $created;

    /**
     * Native implementation of message.
     *
     * @param array<mixed>|null $payload
     * @param float|null $created timestamp (microtime(true))
     * @param float|null $executeAt timestamp (microtime(true))
     */
    public function __construct(
        private string $type,
        private ?array $payload = null,
        ?string $messageId = null,
        ?float $created = null,
        private ?float $executeAt = null,
        private int $retries = 0,
    ) {
        $this->messageId = ($messageId === null || $messageId === '')
            ? Uuid::uuid4()->toString()
            : $messageId;

        $this->created = $created ?? microtime(true);
    }

    #[\Override]
    public function getId(): string
    {
        return $this->messageId;
    }

    #[\Override]
    public function getCreated(): float
    {
        return $this->created;
    }

    #[\Override]
    public function getExecuteAt(): ?float
    {
        return $this->executeAt;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    #[\Override]
    public function getRetries(): int
    {
        return $this->retries;
    }
}
