<?php

namespace Tomaj\Hermes;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Restart\RestartException;
use Tomaj\Hermes\Restart\RestartInterface;
use Tracy\Debugger;
use DateTime;

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
     * Restart
     *
     * @var RestartInterface
     */
    private $restart;

    /**
     * All registered handlers
     *
     * @var HandlerInterface[][]
     */
    private $handlers = [];

    /**
     * @var DateTime
     */
    private $startTime;

    /**
     * Create new Dispatcher
     *
     * @param DriverInterface $driver
     * @param LoggerInterface $logger
     * @param RestartInterface $restart
     */
    public function __construct(DriverInterface $driver, LoggerInterface $logger = null, RestartInterface $restart = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->restart = $restart;
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
     * Basic method for background job to star listening.
     *
     * This method hook to driver wait() method and start listening events.
     * Method is blocking, so when you call it all processing will stop.
     * WARNING! Don't use it on web server calls. Run it only with cli.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->driver->wait(function (MessageInterface $message) {
                $this->log(
                    LogLevel::INFO,
                    "Start handle message #{$message->getId()} ({$message->getType()})",
                    $this->messageLoggerContext($message)
                );

                $result = $this->dispatch($message);

                if ($this->restart && $this->restart->shouldRestart($this->startTime)) {
                    throw new RestartException('Restart');
                }

                return $result;
            });
        } catch (RestartException $e) {
            $this->log(LogLevel::NOTICE, 'Existing hermes dispatcher - restart');
        } catch (Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
        }
    }

    /**
     * Dispatch message
     *
     * @param MessageInterface $message
     *
     * @return bool
     */
    private function dispatch(MessageInterface $message)
    {
        $type = $message->getType();

        if (!$this->hasHandlers($type)) {
            return true;
        }

        $result = true;

        foreach ($this->handlers[$type] as $handler) {
            $handlerResult = $this->handleMessage($handler, $message);

            if ($result && !$handlerResult) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Handle given message with given handler
     *
     * @param HandlerInterface $handler
     * @param MessageInterface $message
     *
     * @return bool
     */
    private function handleMessage(HandlerInterface $handler, MessageInterface $message)
    {
        // check if handler implements Psr\Log\LoggerAwareInterface (you can use \Psr\Log\LoggerAwareTrait)
        if ($this->logger && method_exists($handler, 'setLogger')) {
            $handler->setLogger($this->logger);
        }

        try {
            $result = $handler->handle($message);

            $this->log(
                LogLevel::INFO,
                "End handle message #{$message->getId()} ({$message->getType()})",
                $this->messageLoggerContext($message)
            );
        } catch (Exception $e) {
            $this->log(
                LogLevel::ERROR,
                "Handler " . get_class($handler) . " throws exception - {$e->getMessage()}",
                ['error' => $e, 'message' => $this->messageLoggerContext($message), 'exception' => $e]
            );
            Debugger::log($e, Debugger::EXCEPTION);
            $result = false;
        }
        return $result;
    }

    /**
     * Check if actual dispatcher has handler for given type
     *
     * @param string $type
     *
     * @return bool
     */
    private function hasHandlers($type)
    {
        return isset($this->handlers[$type]) && count($this->handlers[$type]) > 0;
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

    /**
     * Serialize message to logger context
     *
     * @param MessageInterface $message
     *
     * @return array
     */
    private function messageLoggerContext(MessageInterface $message)
    {
        return [
            'id' => $message->getId(),
            'created' => $message->getCreated(),
            'type' => $message->getType(),
            'payload' => $message->getPayload(),
        ];
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
