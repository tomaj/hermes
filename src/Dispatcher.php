<?php

namespace Tomaj\Hermes;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Driver\DriverInterface;

class Dispatcher implements DispatcherInterface
{
    private $driver;

    private $handlers = [];

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function emit(MessageInterface $message)
    {
        $this->driver->send($message);
    }

    public function handle()
    {
        $this->driver->wait(function ($message) {
            echo "New message:";
            print_r($message);
            $this->dispatch($message);
        });
    }

    private function dispatch(MessageInterface $message)
    {
        $type = $message->getType();
        if (isset($this->handlers[$type])) {
            foreach ($this->handlers[$type] as $handler) {
                $handler->handle($message);
            }
        }
    }

    public function registerHandler($type, HandlerInterface $handler)
    {
        if (!isset($this->handlers[$type])) {
            $this->handlers[$type] = [];
        }

        $this->handlers[$type][] = $handler;
    }
}
