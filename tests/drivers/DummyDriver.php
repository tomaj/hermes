<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\MessageInterface;
use Closure;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Driver\SerializerAwareTrait;

class DummyDriver implements DriverInterface
{
    use SerializerAwareTrait;

    private $storage = [];

    private $events = [];

    public function __construct($events = null)
    {
        $this->serializer = new MessageSerializer();

        if (!$events) {
            $events = [];
        }
        foreach ($events as $event) {
            $this->events[] = $this->serializer->serialize($event);
        }
    }

    public function send(MessageInterface $message)
    {
        $this->events[] = $this->serializer->serialize($message);
    }

    public function getMessage()
    {
        $message = array_pop($this->events);
        if (!$message) {
            return null;
        }
        return $this->serializer->unserialize($message);
    }

    public function wait(Closure $callback)
    {
        foreach ($this->events as $event) {
            $message = $this->serializer->unserialize($event);
            $callback($message);
        }
    }
}
