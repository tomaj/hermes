<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Driver\DriverInterface;

final readonly class Emitter implements EmitterInterface
{
    /**
     * Create new Emitter
     */
    public function __construct(
        private DriverInterface $driver,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws Driver\UnknownPriorityException
     */
    #[\Override]
    public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
    {
        $this->driver->send($message, $priority);

        $this->log(
            LogLevel::INFO,
            "Emitter send message #{$message->getId()} to driver " . $this->driver::class,
            $this->messageLoggerContext($message),
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
        ];
    }

    /**
     * Internal log method wrapper
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
