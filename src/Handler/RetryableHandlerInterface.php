<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Throwable;
use Tomaj\Hermes\MessageInterface;

/**
 * A handler whose failed messages the dispatcher may send again.
 *
 * When handle() throws, Dispatcher calls canRetry() with the message and the exception. A retry
 * copy is sent only when it returns true and the message has fewer than maxRetry() retries.
 * RetryTrait implements both methods (always retry, at most 25 times).
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
