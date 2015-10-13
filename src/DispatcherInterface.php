<?php

namespace Tomaj\Hermes;

use Tomaj\Hermes\Handler\HandlerInterface;

interface DispatcherInterface
{
    public function emit(MessageInterface $message);

    public function registerHandler($type, HandlerInterface $handler);
}
