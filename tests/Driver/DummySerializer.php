<?php

declare(strict_types=1);

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
        $decoded = unserialize($string);
        if (!$decoded instanceof MessageInterface) {
            throw new \RuntimeException('Invalid serialized message');
        }

        return $decoded;
    }
}
