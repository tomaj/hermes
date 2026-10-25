<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Random\Randomizer;
use Throwable;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Shutdown\SignalStop;
use Tracy\Debugger;

final class Dispatcher implements DispatcherInterface
{
    public const int DEFAULT_PRIORITY = 100;

    /**
     * All registered handlers
     *
     * @var array<string, array<int, HandlerInterface>>
     */
    private array $handlers = [];

    private readonly DateTimeImmutable $startTime;

    /**
     * Handler classes already reported by reportLegacyRetry(), so each one is reported once per process.
     *
     * @var array<class-string, true>
     */
    private static array $deprecatedRetryHandlers = [];

    private readonly Randomizer $randomizer;

    /**
     * Create new Dispatcher
     *
     * @param Randomizer|null $randomizer Source of the retry jitter. Pass one with a seeded engine
     *                                    (e.g. new Randomizer(new Mt19937(42))) for reproducible retry times.
     */
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ShutdownInterface $shutdown = null,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer();
        $this->startTime = new DateTimeImmutable();

        // check if driver use ShutdownTrait
        if ($this->shutdown instanceof ShutdownInterface && method_exists($this->driver, 'setShutdown')) {
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
            "Dispatcher send message #{$message->getId()} with priority {$priority} to driver " . $this->driver::class,
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
     * An Exception from the driver is logged at error and rethrown, so the worker exits non-zero.
     *
     * @param int[] $priorities
     *
     * @throws Exception
     */
    #[\Override]
    public function handle(array $priorities = []): void
    {
        // stop after the current message on SIGTERM / SIGINT; the previous handlers come back when this returns
        $signals = SignalStop::install();
        try {
            $this->driver->wait(function (MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool {
                $this->log(
                    LogLevel::INFO,
                    "Start handle message #{$message->getId()} ({$message->getType()}) priority:{$priority}",
                    $this->messageLoggerContext($message),
                );

                $result = $this->dispatch($message);

                if (SignalStop::received() !== null || ($this->shutdown instanceof ShutdownInterface && $this->shutdown->shouldShutdown($this->startTime))) {
                    throw new ShutdownException('Shutdown');
                }

                return $result;
            }, $priorities);
        } catch (ShutdownException) {
            $this->log(LogLevel::NOTICE, $this->shutdownReason());
        } catch (Exception $exception) {
            // a driver failure (lost connection, broken payload): log it and let the worker exit non-zero
            $this->log(
                LogLevel::ERROR,
                'Exiting hermes dispatcher - driver error: ' . $exception::class . " - {$exception->getMessage()}",
                ['exception' => $exception],
            );
            $this->reportToTracy($exception);
            throw $exception;
        }
    }

    /**
     * Tracy is optional (suggest): write to it only when it is installed and already enabled.
     */
    private function reportToTracy(Throwable $exception): void
    {
        if (class_exists(Debugger::class) && Debugger::isEnabled()) {
            Debugger::log($exception, Debugger::EXCEPTION);
        }
    }

    private function shutdownReason(): string
    {
        $signal = SignalStop::received();

        return $signal === null ? 'Exiting hermes dispatcher - shutdown' : 'Exiting hermes dispatcher - received ' . SignalStop::describe($signal);
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
        if ($this->logger instanceof LoggerInterface && method_exists($handler, 'setLogger')) {
            $handler->setLogger($this->logger);
        }

        try {
            $result = $handler->handle($message);

            $this->log(
                LogLevel::INFO,
                "End handle message #{$message->getId()} ({$message->getType()})",
                $this->messageLoggerContext($message),
            );
        } catch (Throwable $e) {
            // an Error (TypeError, ...) is a failed message too, not a reason to stop the worker
            $this->log(
                LogLevel::ERROR,
                'Handler ' . $handler::class . " throws exception - {$e->getMessage()}",
                ['error' => $e, 'message' => $this->messageLoggerContext($message), 'exception' => $e],
            );
            $this->reportToTracy($e);

            $this->retryMessage($message, $handler, $e);

            $result = false;
        }
        return $result;
    }

    /**
     * Helper function for sending retrying message back to driver
     */
    private function retryMessage(MessageInterface $message, HandlerInterface $handler, Throwable $exception): void
    {
        if ($handler instanceof RetryableHandlerInterface) {
            $retry = $message->getRetries() < $handler->maxRetry() && $handler->canRetry($message, $exception);
        } elseif (method_exists($handler, 'canRetry') && method_exists($handler, 'maxRetry')) {
            // DEPRECATED since 6.0, remove in 7.0: the 5.x rule for handlers without the interface
            $this->reportLegacyRetry($handler);
            $retry = $message->getRetries() < $handler->maxRetry();
        } else {
            return;
        }
        if (!$retry) {
            return;
        }

        $this->driver->send(new Message(
            $message->getType(),
            $message->getPayload(),
            $message->getId(),
            $message->getCreated(),
            $this->nextRetry($message),
            $message->getRetries() + 1,
        ));
    }

    /**
     * Reports, once per handler class, that the handler is retried by the 5.x rule: it has canRetry()
     * and maxRetry() (its own methods or RetryTrait) but does not implement RetryableHandlerInterface.
     * Such a handler is retried while getRetries() < maxRetry(), without calling canRetry(), as in 5.x.
     *
     * Deprecated since 6.0 and removed in 7.0 together with its branch in retryMessage().
     */
    private function reportLegacyRetry(HandlerInterface $handler): void
    {
        $class = $handler::class;
        if (self::$deprecatedRetryHandlers[$class] ?? false) {
            return;
        }
        self::$deprecatedRetryHandlers[$class] = true;

        $interface = RetryableHandlerInterface::class;
        $notice = "Handler {$class} defines canRetry() and maxRetry() without implementing {$interface}."
            . ' Hermes retries it as 5.x did (canRetry() is not called), which is deprecated since 6.0 and removed in 7.0.'
            . " Implement {$interface}.";
        $this->log(LogLevel::WARNING, $notice, ['handler' => $class]);
        @trigger_error($notice, E_USER_DEPRECATED);
    }

    /**
     * Calculate next retry
     *
     * Inspired by ruby sidekiq (https://github.com/mperham/sidekiq/wiki/Error-Handling#automatic-job-retry)
     */
    private function nextRetry(MessageInterface $message): float
    {
        $retries = $message->getRetries();
        return microtime(true) + $retries ** 4 + 15 + $this->randomizer->getInt(1, 30) * ($retries + 1);
    }

    /**
     * Check if actual dispatcher has handler for given type
     */
    private function hasHandlers(string $type): bool
    {
        return ($this->handlers[$type] ?? []) !== [];
    }

    #[\Override]
    public function registerHandler(string $type, HandlerInterface $handler): DispatcherInterface
    {
        $this->handlers[$type][] = $handler;
        return $this;
    }

    #[\Override]
    public function registerHandlers(string $type, array $handlers): DispatcherInterface
    {
        foreach ($handlers as $handler) {
            $this->registerHandler($type, $handler);
        }
        return $this;
    }

    #[\Override]
    public function unregisterAllHandlers(): DispatcherInterface
    {
        $this->handlers = [];
        return $this;
    }

    #[\Override]
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
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }
}
