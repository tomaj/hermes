<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Throwable;
use Tomaj\Hermes\MessageInterface;

/**
 * Default retry policy: retry every failure, at most 25 times.
 *
 * Use it in a class that implements RetryableHandlerInterface. A handler that uses the trait
 * without the interface is still retried by the deprecated 5.x rule (canRetry() is not called)
 * and triggers E_USER_DEPRECATED; that fallback is removed in 7.0.
 */
trait RetryTrait
{
    public function canRetry(MessageInterface $message, Throwable $exception): bool
    {
        return true;
    }

    public function maxRetry(): int
    {
        return 25;
    }
}
