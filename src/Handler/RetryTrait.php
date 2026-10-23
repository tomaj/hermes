<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Throwable;
use Tomaj\Hermes\MessageInterface;

/**
 * Default retry policy: retry every failure, at most 25 times.
 *
 * The parameters of canRetry() are optional, so the trait fits handlers written for 5.0 and handlers
 * that implement RetryableHandlerInterface, whose canRetry() 6.0 calls with the message and the exception.
 */
trait RetryTrait
{
    public function canRetry(?MessageInterface $message = null, ?Throwable $exception = null): bool
    {
        return true;
    }

    public function maxRetry(): int
    {
        return 25;
    }
}
