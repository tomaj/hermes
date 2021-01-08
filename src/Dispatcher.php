<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use DateTime;
use Exception;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\RestartTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Restart\RestartException;
use Tomaj\Hermes\Restart\RestartInterface;
use Tracy\Debugger;

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
     * @param LoggerInterface|null $logger
     * @param RestartInterface|null $restart
     */
    public function __construct(DriverInterface $driver, LoggerInterface $logger = null, RestartInterface $restart = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
        $this->restart = $restart;
        $this->startTime = new DateTime();


        // check if driver use RestartTrait
        if ($restart && method_exists($this->driver, 'setRestart')) {
            $this->driver->setRestart($restart);
        }
    }

    /**
     * @param MessageInterface $message
     * @return DispatcherInterface
     * @deprecated - use Emitter::emit method instead
     */
    public function emit(MessageInterface $message): DispatcherInterface
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
    public function handle(): void
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
            $this->log(LogLevel::NOTICE, 'Exiting hermes dispatcher - restart');
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
            if (Debugger::isEnabled()) {
                Debugger::log($e, Debugger::EXCEPTION);
            }

            if (method_exists($handler, 'canRetry')) {
                if ($message->getRetries() < $handler->maxRetry()) {
                    $executeAt = $this->nextRetry($message);
                    $newMessage = new Message($message->getType(), $message->getPayload(), $message->getId(), $message->getCreated(), $executeAt, $message->getRetries() + 1);
                    $this->driver->send($newMessage);
                }
            }

            $result = false;
        }
        return $result;
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
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
