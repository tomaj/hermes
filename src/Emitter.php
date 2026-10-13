<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Driver\DriverInterface;

final class Emitter implements EmitterInterface
{
    /**
     * Create new Emitter
     */
    public function __construct(
        private readonly DriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
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
            "Dispatcher send message #{$message->getId()} to driver " . get_class($this->driver),
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
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}
