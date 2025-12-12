<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Driver\DriverInterface;

class Emitter implements EmitterInterface
{
    private DriverInterface $driver;

    private ?LoggerInterface $logger;

    /**
     * Create new Dispatcher
     */
    public function __construct(DriverInterface $driver, ?LoggerInterface $logger = null)
    {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @throws Driver\UnknownPriorityException
     */
    public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
    {
        $this->driver->send($message, $priority);

        $this->log(
            LogLevel::INFO,
            sprintf('Dispatcher send message #%s to driver ', $message->getId()) . get_class($this->driver),
            $this->messageLoggerContext($message)
        );
        return $this;
    }

    /**
     * Serialize message to logger context
     *
     *
     * @return array<mixed>
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
     * @param mixed $level
     * @param array<mixed> $context
     *
     */
    private function log($level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}
