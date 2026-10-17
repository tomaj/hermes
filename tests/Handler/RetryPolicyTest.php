<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;

/**
 * Dispatcher retries a failed message only when the handler's retry policy allows it (#107).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Handler\RetryTrait
 */
class RetryPolicyTest extends TestCase
{
    /**
     * Handles the given message once and records what the dispatcher sends back.
     *
     * @return list<MessageInterface>
     */
    private function retriesAfterFailure(HandlerInterface $handler, int $retries = 0): array
    {
        $driver = new class (new Message('job', ['a' => 1], 'id-1', 1700000000.0, null, $retries)) implements DriverInterface {
            /** @var list<MessageInterface> */
            public array $sent = [];

            public function __construct(private readonly MessageInterface $message) {}

            #[\Override]
            public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
            {
                $this->sent[] = $message;

                return true;
            }

            #[\Override]
            public function setupPriorityQueue(string $name, int $priority): void {}

            #[\Override]
            public function wait(Closure $callback, array $priorities = []): void
            {
                $callback($this->message);
            }
        };
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', $handler);
        $dispatcher->handle();

        return $driver->sent;
    }

    public function testInterfaceHandlerGetsTheMessageAndTheException(): void
    {
        $handler = new PolicyHandler(true, 3);

        $sent = $this->retriesAfterFailure($handler, 1);

        self::assertCount(1, $sent);
        self::assertSame(2, $sent[0]->getRetries());
        self::assertSame('id-1', $sent[0]->getId());
        self::assertCount(1, $handler->calls);
        self::assertSame('id-1', $handler->calls[0][0]->getId());
        self::assertSame(1, $handler->calls[0][0]->getRetries());
        self::assertSame('permanent?', $handler->calls[0][1]->getMessage());
    }

    public function testCanRetryFalseSkipsTheRetry(): void
    {
        $handler = new PolicyHandler(false, 3);

        self::assertSame([], $this->retriesAfterFailure($handler));
        self::assertCount(1, $handler->calls);
    }

    public function testMaxRetryIsTheLimitAndCanRetryIsNotAskedPastIt(): void
    {
        self::assertCount(1, $this->retriesAfterFailure(new PolicyHandler(true, 3), 2));

        $handler = new PolicyHandler(true, 3);
        self::assertSame([], $this->retriesAfterFailure($handler, 3));
        self::assertSame([], $handler->calls);

        self::assertSame([], $this->retriesAfterFailure(new PolicyHandler(true, 0)));
    }

    public function testHandlerWithTheMethodsButNeitherInterfaceNorTraitIsNotRetried(): void
    {
        $handler = new class implements HandlerInterface {
            public function canRetry(): bool
            {
                return true;
            }

            public function maxRetry(): int
            {
                return 25;
            }

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        self::assertSame([], $this->retriesAfterFailure($handler));
    }

    public function testTraitHandlerWithoutTheInterfaceIsStillRetried(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        self::assertCount(1, $this->retriesAfterFailure($handler, 24));
        self::assertSame([], $this->retriesAfterFailure($handler, 25));
    }

    public function testTraitHandlerThatOverridesCanRetryIsAsked(): void
    {
        // 5.x style override without parameters: the dispatcher's arguments are ignored
        $no = new class implements HandlerInterface {
            use RetryTrait;

            public function canRetry(): bool
            {
                return false;
            }

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };
        self::assertSame([], $this->retriesAfterFailure($no));

        $yes = new class implements HandlerInterface {
            use RetryTrait;

            public function canRetry(): int
            {
                return 1; // truthy but not true: only a real true allows the retry
            }

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };
        self::assertSame([], $this->retriesAfterFailure($yes));
    }

    public function testTraitFromAParentClassOrAnotherTraitCounts(): void
    {
        self::assertCount(1, $this->retriesAfterFailure(new ChildOfRetryingHandler()));
        self::assertCount(1, $this->retriesAfterFailure(new HandlerWithNestedTrait()));
        self::assertSame([], $this->retriesAfterFailure(new HandlerWithUnrelatedTrait()));
        // the trait list of the class is kept while the parents are read
        self::assertCount(1, $this->retriesAfterFailure(new RetryingChildOfPlainHandler()));
        // and the other traits are kept while one trait's own traits are read
        self::assertCount(1, $this->retriesAfterFailure(new HandlerWithTwoNestedTraits()));
    }

    public function testTraitImplementsTheInterface(): void
    {
        $handler = new class implements RetryableHandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        self::assertTrue($handler->canRetry(new Message('job', []), new RuntimeException('x')));
        self::assertSame(25, $handler->maxRetry());
        self::assertCount(1, $this->retriesAfterFailure($handler));
    }
}

final class PolicyHandler implements RetryableHandlerInterface
{
    /** @var list<array{MessageInterface, Throwable}> */
    public array $calls = [];

    public function __construct(private readonly bool $retry, private readonly int $max) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('permanent?');
    }

    #[\Override]
    public function canRetry(MessageInterface $message, Throwable $exception): bool
    {
        $this->calls[] = [$message, $exception];

        return $this->retry;
    }

    #[\Override]
    public function maxRetry(): int
    {
        return $this->max;
    }
}

abstract class RetryingBaseHandler implements HandlerInterface
{
    use RetryTrait;
}

final class ChildOfRetryingHandler extends RetryingBaseHandler
{
    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }
}

trait RetriesViaRetryTrait
{
    use RetryTrait;
}

trait UnrelatedTrait
{
    public function canRetry(): bool
    {
        return true;
    }

    public function maxRetry(): int
    {
        return 25;
    }
}

final class HandlerWithNestedTrait implements HandlerInterface
{
    use RetriesViaRetryTrait;

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }
}

final class HandlerWithUnrelatedTrait implements HandlerInterface
{
    use UnrelatedTrait;

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }
}

abstract class PlainBaseHandler implements HandlerInterface {}

final class RetryingChildOfPlainHandler extends PlainBaseHandler
{
    use RetryTrait;

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }
}

trait LeafTrait {}

trait NestsLeafTrait
{
    use LeafTrait;
}

final class HandlerWithTwoNestedTraits implements HandlerInterface
{
    use RetriesViaRetryTrait;
    use NestsLeafTrait;

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }
}
