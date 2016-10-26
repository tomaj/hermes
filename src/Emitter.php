<?php

namespace Tomaj\Hermes;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Driver\DriverInterface;

class Emitter implements DispatcherInterface
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
     * Create new Dispatcher
     *
     * @param DriverInterface $driver
     * @param LoggerInterface $logger
     */
    public function __construct(DriverInterface $driver, LoggerInterface $logger = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->startTime = new DateTime();
    }

    /**
     * {@inheritdoc}
     */
    public function emit(MessageInterface $message)
    {
        $this->driver->send($message);

        $this->log(
            LogLevel::INFO,
            "Dispatcher send message #{$message->getId()} to driver " . get_class($this->driver),
            $this->messageLoggerContext($message)
        );
        return $this;
    }

    /**
     * Interal log method wrapper
     *
     * @param string $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    private function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
