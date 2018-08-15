<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Logger;

use Tomaj\Hermes\Message;
use Psr\Log\LoggerInterface;
use Closure;

class TestLogger implements LoggerInterface
{
    private $data = [];

    public function emergency($message, array $context = array())
    {
        $this->data[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
    }

    public function alert($message, array $context = array())
    {
        $this->data[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
    }

    public function critical($message, array $context = array())
    {
        $this->data[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }

    public function error($message, array $context = array())
    {
        $this->data[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public function warning($message, array $context = array())
    {
        $this->data[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function notice($message, array $context = array())
    {
        $this->data[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
    }

    public function info($message, array $context = array())
    {
        $this->data[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function debug($message, array $context = array())
    {
        $this->data[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    public function log($level, $message, array $context = array())
    {
        $this->data[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function getLogs()
    {
        return $this->data;
    }
}
