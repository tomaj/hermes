<?php

namespace Tomaj\Hermes\Test\Driver;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\SerializerInterface;

class DummySerializer implements SerializerInterface
{
    public function serialize(MessageInterface $message): string
    {
        return serialize($message);
    }

    public function unserialize(string $string): MessageInterface
    {
        return unserialize($string);
    }
}
