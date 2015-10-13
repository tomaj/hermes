<?php

namespace Tomaj\Hermes;

class Message implements MessageInterface
{
    private $type;

    private $payload;

    public function __construct($type, $payload = null)
    {
        $this->type = $type;
        $this->payload = $payload;
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
