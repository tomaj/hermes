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
     * @var string
     */
    private $payload;

    /**
     * @var string
     */
    private $id;

    /**
     * @var DateTime
     */
    private $created;

    /**
     * Native implementation of message.
     *
     * @var string   $type
     * @var string   $payload
     * @var string   $id
     * @var int      $created
     *
     */
    public function __construct($type, $payload = null, $id = null, $created = null)
    {
        if ($id) {
            $this->id = $id;
        } else {
            $this->id = Uuid::uuid4()->toString();
        }
        if ($created) {
            $this->created = $created;
        } else {
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
        return $this->id;
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
