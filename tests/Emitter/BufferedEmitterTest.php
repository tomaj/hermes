<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Emitter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Double\RecordingEmitter;

#[CoversClass(BufferedEmitter::class)]
final class BufferedEmitterTest extends TestCase
{
    private RecordingEmitter $inner;

    #[\Override]
    protected function setUp(): void
    {
        $this->inner = new RecordingEmitter();
    }

    public function testEmitBuffersAndReturnsItself(): void
    {
        $emitter = new BufferedEmitter($this->inner);

        self::assertSame($emitter, $emitter->emit(new Message('a', [], 'a')));
        self::assertSame($emitter, $emitter->emit(new Message('b', [], 'b'), 200));

        self::assertSame([], $this->inner->sent());
        self::assertCount(2, $emitter);
    }

    public function testFlushSendsInOrderWithOriginalPriorities(): void
    {
        $emitter = new BufferedEmitter($this->inner);
        $emitter->emit(new Message('a', [], 'a'));
        $emitter->emit(new Message('b', [], 'b'), 200);
        $emitter->emit(new Message('c', [], 'c'), 50);

        $emitter->flush();

        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY], ['b', 200], ['c', 50]], $this->inner->sent());
        self::assertCount(0, $emitter);

        $emitter->flush();
        self::assertCount(3, $this->inner->sent(), 'a second flush() sends nothing again');
    }

    public function testFlushOnEmptyBufferDoesNotCallTheInnerEmitter(): void
    {
        $calls = 0;
        $this->inner->before = static function () use (&$calls): void {
            $calls++;
        };

        new BufferedEmitter($this->inner)->flush();

        self::assertSame(0, $calls);
        self::assertSame([], $this->inner->sent());
    }

    public function testDiscardDropsAndReturnsCount(): void
    {
        $emitter = new BufferedEmitter($this->inner);
        $emitter->emit(new Message('a', [], 'a'));
        $emitter->emit(new Message('b', [], 'b'));

        self::assertSame(2, $emitter->discard());
        self::assertCount(0, $emitter);
        self::assertSame(0, $emitter->discard());

        $emitter->flush();
        self::assertSame([], $this->inner->sent());
    }

    public function testPredicateFalseSendsRightAway(): void
    {
        $seen = [];
        $emitter = new BufferedEmitter($this->inner, static function (MessageInterface $message, int $priority) use (&$seen): bool {
            $seen[] = [$message->getId(), $priority];

            return $message->getType() === 'buffer';
        });

        $emitter->emit(new Message('now', [], 'n1'), 200);
        $emitter->emit(new Message('buffer', [], 'b1'));
        $emitter->emit(new Message('now', [], 'n2'));

        self::assertSame([['n1', 200], ['b1', Dispatcher::DEFAULT_PRIORITY], ['n2', Dispatcher::DEFAULT_PRIORITY]], $seen, 'the predicate is called on every emit()');
        self::assertSame([['n1', 200], ['n2', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(1, $emitter);
    }

    public function testPredicateIsCalledOnEveryEmitAndCanChangeItsAnswer(): void
    {
        $inTransaction = true;
        $emitter = new BufferedEmitter($this->inner, static function () use (&$inTransaction): bool {
            return $inTransaction;
        });

        $emitter->emit(new Message('a', [], 'a'));
        $inTransaction = false;
        $emitter->emit(new Message('b', [], 'b'));

        self::assertSame([['b', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(1, $emitter);
    }

    public function testArrayCallableIsAccepted(): void
    {
        $transaction = new FakeTransaction();
        $emitter = new BufferedEmitter($this->inner, $transaction->isInTransaction(...));

        $emitter->emit(new Message('a', [], 'a'));
        $transaction->level = 1;
        $emitter->emit(new Message('b', [], 'b'));

        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(1, $emitter);

        $transaction->onCommit($emitter->flush(...));
        $transaction->commit();
        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY], ['b', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
    }

    public function testInternalPredicateWithoutParametersIsCalledWithoutArguments(): void
    {
        // gc_enabled() is internal and takes no parameters, like PDO::inTransaction(): extra arguments throw
        gc_enable();
        $emitter = new BufferedEmitter($this->inner, gc_enabled(...));

        $emitter->emit(new Message('a', [], 'a'));

        self::assertSame([], $this->inner->sent());
        self::assertCount(1, $emitter);
    }

    public function testPartialFailureKeepsTheRestAndRethrowsTheSameException(): void
    {
        $failure = new RuntimeException('broker down');
        $attempts = 0;
        $this->inner->before = static function (MessageInterface $message) use ($failure, &$attempts): void {
            if ($message->getId() === 'b' && $attempts++ === 0) {
                throw $failure;
            }
        };
        $emitter = new BufferedEmitter($this->inner);
        $emitter->emit(new Message('a', [], 'a'));
        $emitter->emit(new Message('b', [], 'b'), 200);
        $emitter->emit(new Message('c', [], 'c'));

        try {
            $emitter->flush();
            self::fail('flush() must rethrow');
        } catch (RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(2, $emitter);

        $emitter->flush();
        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY], ['b', 200], ['c', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(0, $emitter);
    }

    public function testFlushCanRunAgainAfterAFailure(): void
    {
        $calls = 0;
        $this->inner->before = static function () use (&$calls): void {
            if ($calls++ === 0) {
                throw new RuntimeException('once');
            }
        };
        $emitter = new BufferedEmitter($this->inner);
        $emitter->emit(new Message('a', [], 'a'));

        try {
            $emitter->flush();
            self::fail('the first flush() must throw');
        } catch (RuntimeException) {
        }
        $emitter->flush();

        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent(), 'the re-entrancy guard is released after an exception');
    }

    public function testMessageEmittedDuringFlushIsSentInTheSameFlush(): void
    {
        $emitter = new BufferedEmitter($this->inner);
        $this->inner->before = static function (MessageInterface $message) use ($emitter): void {
            if ($message->getId() === 'a') {
                $emitter->emit(new Message('followup', [], 'followup'), 50);
            }
        };
        $emitter->emit(new Message('a', [], 'a'));
        $emitter->emit(new Message('b', [], 'b'));

        $emitter->flush();

        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY], ['b', Dispatcher::DEFAULT_PRIORITY], ['followup', 50]], $this->inner->sent());
        self::assertCount(0, $emitter);
    }

    public function testReentrantFlushSendsNothingTwice(): void
    {
        $emitter = new BufferedEmitter($this->inner);
        $nested = 0;
        $this->inner->before = static function () use ($emitter, &$nested): void {
            $nested++;
            $emitter->flush();
        };
        $emitter->emit(new Message('a', [], 'a'));
        $emitter->emit(new Message('b', [], 'b'));

        $emitter->flush();

        self::assertSame(2, $nested);
        self::assertSame([['a', Dispatcher::DEFAULT_PRIORITY], ['b', Dispatcher::DEFAULT_PRIORITY]], $this->inner->sent());
        self::assertCount(0, $emitter);
    }
}

/**
 * Stand-in for a database transaction object with a level counter and commit callbacks.
 */
final class FakeTransaction
{
    public int $level = 0;

    /** @var list<callable(): mixed> */
    private array $onCommit = [];

    public function isInTransaction(): bool
    {
        return $this->level > 0;
    }

    /**
     * @param callable(): mixed $callback
     */
    public function onCommit(callable $callback): void
    {
        $this->onCommit[] = $callback;
    }

    public function commit(): void
    {
        $this->level = 0;
        foreach ($this->onCommit as $callback) {
            $callback();
        }
    }
}
