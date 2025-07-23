<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use Ramsey\Uuid\Uuid;

class Message implements MessageInterface
{
    private string $type;

    /**
     * @var array<mixed>|null
     */
    private ?array $payload;

    private string $messageId;

    private float $created;

    private ?float $executeAt;

    private int $retries;

    /**
     * Native implementation of message.
     *
     * @param string $type
     * @param array<mixed>|null $payload
     * @param string|null $messageId
     * @param float|null $created timestamp (microtime(true))
     * @param float|null $executeAt timestamp (microtime(true))
     * @param int $retries
     */
    public function __construct(string $type, ?array $payload = null, ?string $messageId = null, ?float $created = null, ?float $executeAt = null, int $retries = 0)
    {
        if ($messageId === null || $messageId === '') {
            $messageId = Uuid::uuid4()->toString();
        }
        $this->messageId = $messageId;

        if ($created === null) {
            $created = microtime(true);
        }
        $this->created = $created;

        $this->type = $type;
        $this->payload = $payload;
        $this->executeAt = $executeAt;
        $this->retries = $retries;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->messageId;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreated(): float
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecuteAt(): ?float
    {
        return $this->executeAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * {@inheritdoc}
     */
    public function getRetries(): int
    {
        return $this->retries;
    }
}
