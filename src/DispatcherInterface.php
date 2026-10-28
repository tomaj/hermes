<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Tomaj\Hermes\Handler\HandlerInterface;

interface DispatcherInterface
{
    /**
     * Register new handler
     *
     * With this method you can register new handler for selected $type.
     * This handler will be called in background job when event
     * of registered $type will be emitted.
     *
     * @return $this
     */
    public function registerHandler(string $type, HandlerInterface $handler): DispatcherInterface;

    /**
     * Register multiple handlers for same type.
     *
     * @param HandlerInterface[] $handlers
     */
    public function registerHandlers(string $type, array $handlers): DispatcherInterface;

    /**
     * Will unregister all handlers. This method is useful for testing.
     */
    public function unregisterAllHandlers(): DispatcherInterface;

    /**
     * Will unregister a specific handler.
     */
    public function unregisterHandler(string $type, HandlerInterface $handler): DispatcherInterface;

    /**
     * Basic method for background job to star listening.
     *
     * @param array<int|string> $priorities Priorities to process, all when empty. Numeric strings
     *                                              (from argv or console options) are cast to int.
     *
     * @throws \InvalidArgumentException when a priority is a string that is not an integer
     */
    public function handle(array $priorities = []): void;
}
