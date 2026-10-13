<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tracy\Debugger;

class Dispatcher implements DispatcherInterface
{
    public const DEFAULT_PRIORITY = 100;

    /**
     * All registered handlers
     *
     * @var array<string, array<int, HandlerInterface>>
     */
    private array $handlers = [];

    private readonly DateTime $startTime;

    /**
     * Create new Dispatcher
     */
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ShutdownInterface $shutdown = null,
    ) {
        $this->startTime = new DateTime();

        // check if driver use ShutdownTrait
        if ($this->shutdown !== null && method_exists($this->driver, 'setShutdown')) {
            $this->driver->setShutdown($this->shutdown);
        }
    }

    /**
     * @return $this
     *
     * @deprecated - use Emitter::emit method instead
     */
    public function emit(MessageInterface $message, int $priority = self::DEFAULT_PRIORITY): DispatcherInterface
    {
        $this->driver->send($message, $priority);

        $this->log(
            LogLevel::INFO,
            "Dispatcher send message #{$message->getId()} with priority {$priority} to driver " . get_class($this->driver),
            $this->messageLoggerContext($message),
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
     * @param int[] $priorities
     */
    public function handle(array $priorities = []): void
    {
        try {
            $this->driver->wait(function (MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool {
                $this->log(
                    LogLevel::INFO,
                    "Start handle message #{$message->getId()} ({$message->getType()}) priority:{$priority}",
                    $this->messageLoggerContext($message),
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
                $this->messageLoggerContext($message),
            );
        } catch (Exception $e) {
            $this->log(
                LogLevel::ERROR,
                'Handler ' . get_class($handler) . " throws exception - {$e->getMessage()}",
                ['error' => $e, 'message' => $this->messageLoggerContext($message), 'exception' => $e],
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
     */
    private function nextRetry(MessageInterface $message): float
    {
        return microtime(true) + pow($message->getRetries(), 4) + 15 + (rand(1, 30) * ($message->getRetries() + 1));
    }

    /**
     * Check if actual dispatcher has handler for given type
     */
    private function hasHandlers(string $type): bool
    {
        return isset($this->handlers[$type]) && count($this->handlers[$type]) > 0;
    }

    public function registerHandler(string $type, HandlerInterface $handler): DispatcherInterface
    {
        if (!isset($this->handlers[$type])) {
            $this->handlers[$type] = [];
        }

        $this->handlers[$type][] = $handler;
        return $this;
    }

    public function registerHandlers(string $type, array $handlers): DispatcherInterface
    {
        foreach ($handlers as $handler) {
            $this->registerHandler($type, $handler);
        }
        return $this;
    }

    public function unregisterAllHandlers(): DispatcherInterface
    {
        $this->handlers = [];
        return $this;
    }

    public function unregisterHandler(string $type, HandlerInterface $handler): DispatcherInterface
    {
        if (!isset($this->handlers[$type])) {
            return $this;
        }
        $this->handlers[$type] = array_filter(
            $this->handlers[$type],
            fn(HandlerInterface $registeredHandler): bool => $registeredHandler !== $handler,
        );

        return $this;
    }

    /**
     * Serialize message to logger context
     *
     * @return array<string, mixed>
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
     * @param array<mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}
