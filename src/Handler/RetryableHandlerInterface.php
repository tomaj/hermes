<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Throwable;
use Tomaj\Hermes\MessageInterface;

/**
 * A handler whose failed messages the dispatcher may send again.
 *
 * Added in 5.1 so handlers can be written for 6.0 now. For a handler that implements it, Dispatcher calls
 * canRetry() with the message and the exception, as 6.0 does, and sends a retry only when it returns
 * true and the message has fewer than maxRetry() retries. RetryTrait implements both methods.
 */
interface RetryableHandlerInterface extends HandlerInterface
{
    /**
     * Return false to skip the retry, for example for a permanent error such as invalid input.
     */
    public function canRetry(MessageInterface $message, Throwable $exception): bool;

    /**
     * How many retries a message gets at most. 0 turns retries off.
     */
    public function maxRetry(): int;
}
