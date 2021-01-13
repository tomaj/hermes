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
     * @param string             $type
     * @param HandlerInterface   $handler
     *
     * @return $this
     */
    public function registerHandler(string $type, HandlerInterface $handler): DispatcherInterface;

    /**
     * Register multiple handlers for same type.
     *
     * @param string $type
     * @param HandlerInterface[] $handler
     * @return DispatcherInterface
     */
    public function registerHandlers(string $type, array $handler): DispatcherInterface;

    /**
     * Basic method for background job to star listening.
     *
     * @param int[] $priorities
     */
    public function handle(array $priorities = []): void;
}
