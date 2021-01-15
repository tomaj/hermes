<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\MaxItemsTrait;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\SerializerAwareTrait;
use Tomaj\Hermes\Driver\NotSupportedException;

class DummyDriver implements DriverInterface
{
    use SerializerAwareTrait;
    use MaxItemsTrait;

    /** @var array<int, array<string>> */
    private $events = [];

    /** @var string[] */
    private $queues = [];

    /** @var bool */
    private $waitResult = null;

    /**
     * DummyDriver constructor.
     * @param MessageInterface[] $events
     *
     * @throws UnknownPriorityException
     * @throws NotSupportedException
     */
    public function __construct(array $events = [])
    {
        $this->serializer = new MessageSerializer();
        $this->setupPriorityQueue('medium', Dispatcher::DEFAULT_PRIORITY);

        foreach ($events as $event) {
            $this->addEvent($this->serializer->serialize($event), Dispatcher::DEFAULT_PRIORITY);
        }
    }

    /**
     * @param string $event
     * @param int $priority
     *
     * @throws UnknownPriorityException
     */
    private function addEvent(string $event, int $priority): void
    {
        if (!isset($this->events[$priority])) {
            throw new UnknownPriorityException("Unknown priority {$priority} - you have to setupPriorityQueue before");
        }
        $this->events[$priority][] = $event;
    }

    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
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

    public function getMessage(): ?MessageInterface
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

    /**
     * @param Closure $callback
     * @param int[] $priorities
     */
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

    public function waitResult(): bool
    {
        return $this->waitResult;
    }
}
