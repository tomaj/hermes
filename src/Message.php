<?php

namespace Tomaj\Hermes;

use Rhumsaa\Uuid\Uuid;

class Message implements MessageInterface
{
    private $type;

    private $payload;

    private $id;

    private $created;

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
            $this->created = time();
        }
        $this->type = $type;
        $this->payload = $payload;
        
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getPayload()
    {
        return $this->payload;
    }
}
