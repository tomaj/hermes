<?php

namespace Tomaj\Hermes;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Driver\DriverInterface;

class Dispatcher implements DispatcherInterface
{
    /**
     * Dispatcher driver
     *
     * @var DriverInterface
     */
    private $driver;

    /**
     * All registered handalers
     *
     * @var array
     */
    private $handlers = [];

    /**
     * Create new Dispatcher
     *
     * @param DriverInterface $driver
     *
     * @return $this
     */
    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(MessageInterface $message)
    {
        $this->driver->send($message);
        return $this;
    }

    /**
     * Basic method for background job to star listening.
     *
     * This method hook to driver wait() method and start listening events.
     * Method is blockig, so when you call it all processing will stop.
     * WARNING! Dont use it on web server calls. Run it only with cli.
     *
     * @return void
     */
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

    /**
     * {@inheritdoc}
     */
    public function registerHandler($type, HandlerInterface $handler)
    {
        if (!isset($this->handlers[$type])) {
            $this->handlers[$type] = [];
        }

        $this->handlers[$type][] = $handler;
    }
}
