<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

interface DriverInterface
{
    /**
     * Send message
     *
     * In this method you have to implement logic to insert messages to process in queue.
     * This method has to be as fast as possible because it will be called in
     * web server threads.
     *
     * @throws SerializeException
     * @throws UnknownPriorityException
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;

    /**
     * Setup new queue with priority.
     * Each message can be directed to specific queue.
     * In general you messages will be processed based on queue (higher priority will be processed first)
     * Or you can define dispatcher that will handle only specific priority (queue)s
     *
     * @param int $priority higher priority will be processed first
     * @throws NotSupportedException
     */
    public function setupPriorityQueue(string $name, int $priority): void;

    /**
     * Processing wait method.
     *
     * Dispatcher will call this method for receiving data from driver.
     * This can be implemented as infinite loop and checking driver in periodic time for new messages
     * or can be implemented as callback for driver emit method (like rabbitmq or redis pubsub).
     * When driver receive new message, you have to call $callback with this message like $callback($message)
     *
     * @param int[] $priorities
     *
     * @throws UnknownPriorityException
     * @throws ShutdownException
     */
    public function wait(Closure $callback, array $priorities): void;
}
