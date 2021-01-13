<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use Tomaj\Hermes\Message;
use Psr\Log\LoggerInterface;
use Closure;

class TestLogger implements LoggerInterface
{
    /** @var array<array<string, mixed>> */
    private $data = [];

    public function emergency($message, array $context = []): void
    {
        $this->data[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
    }

    public function alert($message, array $context = []): void
    {
        $this->data[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
    }

    public function critical($message, array $context = []): void
    {
        $this->data[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }

    public function error($message, array $context = []): void
    {
        $this->data[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public function warning($message, array $context = []): void
    {
        $this->data[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function notice($message, array $context = []): void
    {
        $this->data[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
    }

    public function info($message, array $context = []): void
    {
        $this->data[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function debug($message, array $context = []): void
    {
        $this->data[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    public function log($level, $message, array $context = []): void
    {
        $this->data[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getLogs(): array
    {
        return $this->data;
    }
}
