<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\MaxItemsTrait;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\SerializerAwareTrait;

class DummyDriver implements DriverInterface
{
    use SerializerAwareTrait;
    use MaxItemsTrait;

    private $events = [];

    private $queues = [];

    private $waitResult = null;

    public function __construct($events = null)
    {
        $this->serializer = new MessageSerializer();
        $this->setupPriorityQueue('medium', Dispatcher::PRIORITY_MEDIUM);

        if (!$events) {
            $events = [];
        }
        foreach ($events as $event) {
            $this->addEvent($this->serializer->serialize($event), Dispatcher::PRIORITY_MEDIUM);
        }
    }

    private function addEvent(string $event, int $priority)
    {
        if (!isset($this->events[$priority])) {
            throw new \Exception("Unknown priority {$priority} - you have to setupPriorityQueue before");
        }
        $this->events[$priority][] = $event;
    }

    public function send(MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM): bool
    {
        $this->addEvent($this->serializer->serialize($message), $priority);
        return true;
    }

    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
        ksort($this->queues, SORT_ASC | SORT_NUMERIC);

        if (!isset($this->events[$priority])) {
            $this->events[$priority] = [];
        }
    }

    public function getMessage()
    {
        $message = null;
        $queues = array_reverse($this->queues, true);
        foreach ($queues as $priority => $queueName) {
            if (count($this->events[$priority]) > 0) {
                $message = array_pop($this->events[$priority]);
                break;
            }
        }

        if ($message === null) {
            return null;
        }
        return $this->serializer->unserialize($message);
    }

    public function wait(Closure $callback, array $priorities = []): void
    {
        $queues = array_reverse($this->queues, true);

        foreach ($queues as $priority => $queueName) {
            foreach ($this->events[$priority] as $event) {
                if (!$this->shouldProcessNext()) {
                    return;
                }
                $message = $this->serializer->unserialize($event);
                $this->waitResult = $callback($message);
                $this->incrementProcessedItems();
            }
        }
    }

    public function waitResult()
    {
        return $this->waitResult;
    }
}
