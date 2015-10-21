<?php

namespace Tomaj\Hermes;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

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
    public function __construct(DriverInterface $driver, LoggerInterface $logger = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(MessageInterface $message)
    {
        $this->driver->send($message);
        $this->log(LogLevel::INFO, "Dispatcher send message #{$message->getId()} to driver " . get_class($this->driver), $this->messageLoggerContext($message));
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
            $this->log(LogLevel::INFO, "Start handle message #{$message->getId()} ({$message->getType()})", $this->messageLoggerContext($message));
            $this->dispatch($message);
        });
    }

    private function dispatch(MessageInterface $message)
    {
        $type = $message->getType();

        if (!isset($this->handlers[$type])) {
            return;
        }

        foreach ($this->handlers[$type] as $handler) {
            // check if handler implements Psr\Log\LoggerAwareInterface (you can use \Psr\Log\LoggerAwareTrait)
            if ($this->logger and method_exists($handler, 'setLogger')) {
                $handler->setLogger($this->logger)
            }

            try {
                $handler->handle($message);
                $this->log(LogLevel::INFO, "End handle message #{$message->getId()} ({$message->getType()})", $this->messageLoggerContext($message));
            } catch (Exception $e) {
                $handlerClass = get_class($handler);
                var_dump($e);
                $this->log(LogLevel::ERROR, "Handler {$handlerClass} throws exception - {$e->getMessage()}", ['error' => $e, 'message' => $this->messageLoggerContext($message), 'exception' => $e]);
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

    private function messageLoggerContext(MessageInterface $message)
    {
        return [
            'id' => $message->getId(),
            'created' => $message->getCreated(),
            'type' => $message->getType(),
            'payload' => $message->getPayload(),
        ];
    }

    private function log($level, $message, $context)
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
