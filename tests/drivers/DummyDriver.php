<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\Message;
use Closure;

class DummyDriver implements DriverInterface
{
    private $storage = [];

    private $events = [];

    public function __construct($events = null)
    {
        if (!$events) {
            $events = [];
        }
        $this->events = $events;
    }

    public function send(Message $message)
    {
        $this->storage[] = $message;
    }

    public function getMessage()
    {
        return array_pop($this->storage);
    }

    public function wait(Closure $callback)
    {
        foreach ($this->events as $event) {
            $callback($event);
        }
    }
}
