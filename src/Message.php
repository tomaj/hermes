<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use Ramsey\Uuid\Uuid;

class Message implements MessageInterface
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var string
     */
    private $messageId;

    /**
     * @var string
     */
    private $created;

    /**
     * @var string
     */
    private $executeAt;

    /**
     * Native implementation of message.
     *
     * @var string   $type
     * @var array    $payload
     * @var string   $messageId
     * @var float    $created   timestamp (microtime(true))
     * @var float    $executeAt timestamp (microtime(true))
     */
    public function __construct(string $type, array $payload = null, string $messageId = null, float $created = null, float $executeAt = null)
    {
        $this->messageId = $messageId;
        if (!$messageId) {
            $this->messageId = Uuid::uuid4()->toString();
        }
        $this->created = $created;
        if (!$created) {
            $this->created = microtime(true);
        }
        $this->type = $type;
        $this->payload = $payload;
        $this->executeAt = $executeAt;
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
}
