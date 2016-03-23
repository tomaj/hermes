<?php

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
     * Native implementation of message.
     *
     * @var string   $type
     * @var array    $payload
     * @var string   $messageId
     * @var string   $created   timestamp (micro)
     *
     */
    public function __construct($type, array $payload = null, $messageId = null, $created = null)
    {
        $this->messageId = $messageId;
        if (!$messageId) {
            $this->messageId = Uuid::uuid4()->toString();
        }
        $this->created = $created;
        if (!$created) {
            $this->created = microtime();
        }
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->messageId;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload()
    {
        return $this->payload;
    }
}
