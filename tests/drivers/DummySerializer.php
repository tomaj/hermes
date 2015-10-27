<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\SerializerInterface;

class DummySerializer implements SerializerInterface
{
    public function serialize(MessageInterface $message)
    {
        return serialize($message);
    }

    public function unserialize($string)
    {
        return unserialize($string);
    }
}
