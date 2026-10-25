<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Closure;
use Countable;
use ReflectionFunction;
use Throwable;

/**
 * Holds emitted messages in memory and sends them to the inner emitter on flush().
 *
 * Use it to emit messages after a database transaction commits: call flush() from the
 * commit hook and discard() from the rollback hook. The buffer lives only in the PHP
 * process, so it is not a transactional outbox; messages are lost when the process dies
 * between the commit and flush().
 */
final class BufferedEmitter implements EmitterInterface, Countable
{
    /** @var list<array{MessageInterface, int}> */
    private array $buffer = [];

    private readonly ?Closure $shouldBuffer;

    private bool $flushing = false;

    /**
     * @param (callable(MessageInterface, int): bool)|(callable(): bool)|null $shouldBuffer Called on every
     *        emit() with the message and the priority; anything but true sends the message right away. A callable
     *        without parameters, such as [$pdo, 'inTransaction'], is called without arguments. Null always buffers.
     */
    public function __construct(
        private readonly EmitterInterface $emitter,
        ?callable $shouldBuffer = null,
    ) {
        if ($shouldBuffer === null) {
            $this->shouldBuffer = null;

            return;
        }

        $closure = $shouldBuffer(...);
        // internal functions such as PDO::inTransaction() throw ArgumentCountError for extra arguments
        $this->shouldBuffer = new ReflectionFunction($closure)->getNumberOfParameters() === 0
            ? static fn(): mixed => $closure()
            : $closure;
    }

    #[\Override]
    public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
    {
        if ($this->shouldBuffer instanceof Closure && ($this->shouldBuffer)($message, $priority) !== true) {
            $this->emitter->emit($message, $priority);

            return $this;
        }

        $this->buffer[] = [$message, $priority];

        return $this;
    }

    /**
     * Sends the buffered messages in the order they were emitted, with their priorities.
     *
     * A message emitted while flush() runs is sent by the same flush(). When the inner emitter
     * throws, the messages sent before stay sent, the failed one and the rest stay buffered and
     * the exception is rethrown; call flush() again to send them, or discard() to drop them.
     * A flush() called while another flush() runs returns without sending anything.
     *
     * @throws Throwable from the inner emitter
     */
    public function flush(): void
    {
        if ($this->flushing) {
            return;
        }

        $this->flushing = true;
        try {
            while ($this->buffer !== []) {
                [$message, $priority] = $this->buffer[0];
                $this->emitter->emit($message, $priority);
                array_shift($this->buffer);
            }
        } finally {
            $this->flushing = false;
        }
    }

    /**
     * Drops the buffered messages without sending them.
     *
     * @return int number of dropped messages
     */
    public function discard(): int
    {
        $count = count($this->buffer);
        $this->buffer = [];

        return $count;
    }

    /**
     * Number of buffered messages.
     */
    #[\Override]
    public function count(): int
    {
        return count($this->buffer);
    }
}
