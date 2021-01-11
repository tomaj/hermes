<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use DateTime;
use Exception;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tracy\Debugger;

class Dispatcher implements DispatcherInterface
{
    const PRIORITY_LOW = 100;
    const PRIORITY_MEDIUM = 200;
    const PRIORITY_HIGH = 300;

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
     * Shutdown
     *
     * @var ShutdownInterface
     */
    private $shutdown;

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
     * @param LoggerInterface|null $logger
     * @param ShutdownInterface|null $shutdown
     */
    public function __construct(DriverInterface $driver, LoggerInterface $logger = null, ShutdownInterface $shutdown = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->shutdown = $shutdown;
        $this->startTime = new DateTime();

        // check if driver use ShutdownTrait
        if ($shutdown && method_exists($this->driver, 'setShutdown')) {
            $this->driver->setShutdown($shutdown);
        }
    }

    /**
     * @param MessageInterface $message
     * @param int $priority
     * @return DispatcherInterface
     * @deprecated - use Emitter::emit method instead
     */
    public function emit(MessageInterface $message, int $priority = self::PRIORITY_MEDIUM): DispatcherInterface
    {
        $this->driver->send($message, $priority);

        $this->log(
            LogLevel::INFO,
            "Dispatcher send message #{$message->getId()} with priority {$priority} to driver " . get_class($this->driver),
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
     * @param array $priorities
     *
     * @return void
     */
    public function handle(array $priorities = []): void
    {
        try {
            $this->driver->wait(function (MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM) {
                $this->log(
                    LogLevel::INFO,
                    "Start handle message #{$message->getId()} ({$message->getType()}) priority:{$priority}",
                    $this->messageLoggerContext($message)
                );

                $result = $this->dispatch($message);

                if ($this->shutdown !== null && $this->shutdown->shouldShutdown($this->startTime)) {
                    throw new ShutdownException('Shutdown');
                }

                return $result;
            }, $priorities);
        } catch (ShutdownException $e) {
            $this->log(LogLevel::NOTICE, 'Exiting hermes dispatcher - shutdown');
        } catch (Exception $exception) {
            if (Debugger::isEnabled()) {
                Debugger::log($exception, Debugger::EXCEPTION);
            }
        }
    }

    /**
     * Dispatch message
     *
     * @param MessageInterface $message
     *
     * @return bool
     */
    private function dispatch(MessageInterface $message): bool
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
    private function handleMessage(HandlerInterface $handler, MessageInterface $message): bool
    {
        // check if handler implements Psr\Log\LoggerAwareInterface (you can use \Psr\Log\LoggerAwareTrait)
        if ($this->logger !== null && method_exists($handler, 'setLogger')) {
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
            if (Debugger::isEnabled()) {
                Debugger::log($e, Debugger::EXCEPTION);
            }

            $this->retryMessage($message, $handler);

            $result = false;
        }
        return $result;
    }

    /**
     * Helper function for sending retrying message back to driver
     *
     * @param MessageInterface $message
     * @param HandlerInterface $handler
     */
    private function retryMessage(MessageInterface $message, HandlerInterface $handler): void
    {
        if (method_exists($handler, 'canRetry') && method_exists($handler, 'maxRetry')) {
            if ($message->getRetries() < $handler->maxRetry()) {
                $executeAt = $this->nextRetry($message);
                $newMessage = new Message($message->getType(), $message->getPayload(), $message->getId(), $message->getCreated(), $executeAt, $message->getRetries() + 1);
                $this->driver->send($newMessage);
            }
        }
    }

    /**
     * Calculate next retry
     *
     * Inspired by ruby sidekiq (https://github.com/mperham/sidekiq/wiki/Error-Handling#automatic-job-retry)
     *
     * @param MessageInterface $message
     * @return float
     */
    private function nextRetry(MessageInterface $message): float
    {
        return microtime(true) + pow($message->getRetries(), 4) + 15 + (rand(1, 30) * ($message->getRetries() + 1));
    }

    /**
     * Check if actual dispatcher has handler for given type
     *
     * @param string $type
     *
     * @return bool
     */
    private function hasHandlers(string $type): bool
    {
        return isset($this->handlers[$type]) && count($this->handlers[$type]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function registerHandler(string $type, HandlerInterface $handler): DispatcherInterface
    {
        if (!isset($this->handlers[$type])) {
            $this->handlers[$type] = [];
        }

        $this->handlers[$type][] = $handler;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function registerHandlers(string $type, array $handlers): DispatcherInterface
    {
        foreach ($handlers as $handler) {
            $this->registerHandler($type, $handler);
        }
        return $this;
    }

    /**
     * Serialize message to logger context
     *
     * @param MessageInterface $message
     *
     * @return array
     */
    private function messageLoggerContext(MessageInterface $message): array
    {
        return [
            'id' => $message->getId(),
            'created' => $message->getCreated(),
            'type' => $message->getType(),
            'payload' => $message->getPayload(),
            'retries' => $message->getRetries(),
            'execute_at' => $message->getExecuteAt(),
        ];
    }

    /**
     * Interal log method wrapper
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    private function log($level, string $message, array $context = array()): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}
