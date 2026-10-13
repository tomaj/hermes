<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use Psr\Log\LoggerInterface;
use Stringable;

class TestLogger implements LoggerInterface
{
    /** @var list<array{level: mixed, message: string|Stringable, context: array<mixed>}> */
    private array $data = [];

    #[\Override]
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    #[\Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->data[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    /**
     * @return list<array{level: mixed, message: string|Stringable, context: array<mixed>}>
     */
    public function getLogs(): array
    {
        return $this->data;
    }
}
