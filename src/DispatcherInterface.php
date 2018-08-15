<?php

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
}
