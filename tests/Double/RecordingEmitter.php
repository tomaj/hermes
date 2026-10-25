<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Double;

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\MessageInterface;

/**
 * Records every emit() as [message id, priority]. An optional hook runs before a message is recorded;
 * it may throw to simulate a failing driver.
 */
final class RecordingEmitter implements EmitterInterface
{
    /** @var list<array{string, int}> */
    private array $sent = [];

    /** @var (Closure(MessageInterface, int): void)|null */
    public ?Closure $before = null;

    #[\Override]
    public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
    {
        if ($this->before instanceof Closure) {
            ($this->before)($message, $priority);
        }
        $this->sent[] = [$message->getId(), $priority];

        return $this;
    }

    /**
     * @return list<array{string, int}> [message id, priority] in the order emit() was called
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
