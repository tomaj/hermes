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
     * @var float
     */
    private $created;

    /**
     * @var string
     */
    private $executeAt;

    /**
     * @var int
     */
    private $retries;

    /**
     * Native implementation of message.
     *
     * @var string   $type
     * @var array    $payload
     * @var string   $messageId
     * @var float    $created   timestamp (microtime(true))
     * @var float    $executeAt timestamp (microtime(true))
     * @var int      $retries
     */
    public function __construct(string $type, array $payload = null, string $messageId = null, float $created = null, float $executeAt = null, int $retries = 0)
    {
        $this->messageId = $messageId;
        if (!$messageId) {
            try {
                $this->messageId = Uuid::uuid4()->toString();
            } catch (\Exception $e) {
                $this->messageId = rand(10000, 99999999);
            }

        }
        $this->created = $created;
        if (!$created) {
            $this->created = microtime(true);
        }
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
