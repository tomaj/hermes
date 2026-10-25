<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Logger\TestLogger;

/**
 * Dispatcher retries a failed message only when the handler's retry policy allows it (#107).
 * A handler with canRetry()/maxRetry() but without RetryableHandlerInterface follows the deprecated 5.x rule.
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
    private function retriesAfterFailure(HandlerInterface $handler, int $retries = 0, ?TestLogger $logger = null): array
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
        $dispatcher = new Dispatcher($driver, $logger);
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

    public function testHandlerWithOwnMethodsButWithoutTheInterfaceIsRetriedAsIn5x(): void
    {
        $handler = new LegacyOwnMethodsHandler(false, 3);

        // canRetry() returns false, but 5.x never called it: the message is retried anyway
        $sent = $this->retriesAfterFailure($handler, 2);
        self::assertCount(1, $sent);
        self::assertSame(3, $sent[0]->getRetries());
        self::assertSame('id-1', $sent[0]->getId());
        self::assertSame(0, $handler->canRetryCalls);

        // maxRetry() is still the limit
        self::assertSame([], $this->retriesAfterFailure($handler, 3));
        self::assertSame([], $this->retriesAfterFailure(new LegacyOwnMethodsHandler(true, 0)));
        self::assertSame(0, $handler->canRetryCalls);
    }

    public function testTraitHandlerWithoutTheInterfaceIsRetriedAsIn5x(): void
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

    public function testTraitHandlerWithoutTheInterfaceIgnoresItsCanRetryOverrideAsIn5x(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            public int $calls = 0;

            public function canRetry(): bool
            {
                ++$this->calls;

                return false;
            }

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        self::assertCount(1, $this->retriesAfterFailure($handler));
        self::assertSame(0, $handler->calls);
    }

    public function testRetryMethodsFromAParentClassOrATraitCount(): void
    {
        self::assertCount(1, $this->retriesAfterFailure(new ChildOfRetryingHandler()));
        self::assertCount(1, $this->retriesAfterFailure(new HandlerWithNestedTrait()));
        self::assertCount(1, $this->retriesAfterFailure(new HandlerWithUnrelatedTrait()));
        self::assertCount(1, $this->retriesAfterFailure(new RetryingChildOfPlainHandler()));
        self::assertCount(1, $this->retriesAfterFailure(new HandlerWithTwoNestedTraits()));
    }

    public function testHandlerWithOnlyOneOfTheMethodsIsNotRetried(): void
    {
        $handler = new class implements HandlerInterface {
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

        $deprecations = $this->captureDeprecations(fn(): array => $this->retriesAfterFailure($handler), $sent);
        self::assertSame([], $sent);
        self::assertSame([], $deprecations);
    }

    public function testLegacyHandlerTriggersOneDeprecationAndWarningPerClass(): void
    {
        $logger = new TestLogger();
        $first = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };
        $second = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        $deprecations = $this->captureDeprecations(function () use ($first, $second, $logger): array {
            $this->retriesAfterFailure($first, 0, $logger);
            $this->retriesAfterFailure(clone $first, 0, $logger);
            // a new dispatcher does not report the class again either
            $this->retriesAfterFailure($first, 0, $logger);

            return $this->retriesAfterFailure($second, 0, $logger);
        }, $sent);

        self::assertCount(1, $sent);
        self::assertCount(2, $deprecations);
        foreach ([$first, $second] as $i => $handler) {
            self::assertSame(
                'Handler ' . $handler::class . ' defines canRetry() and maxRetry() without implementing Tomaj\\Hermes\\Handler\\RetryableHandlerInterface.'
                . ' Hermes retries it as 5.x did (canRetry() is not called), which is deprecated since 6.0 and removed in 7.0.'
                . ' Implement Tomaj\\Hermes\\Handler\\RetryableHandlerInterface.',
                $deprecations[$i],
            );
        }

        $warnings = array_values(array_filter($logger->getLogs(), fn(array $record): bool => $record['level'] === LogLevel::WARNING));
        self::assertCount(2, $warnings);
        self::assertSame($deprecations[0], $warnings[0]['message']);
        self::assertSame(['handler' => $first::class], $warnings[0]['context']);
        self::assertSame($deprecations[1], $warnings[1]['message']);
    }

    public function testDeprecationIsReportedEvenWhenTheRetryLimitIsReached(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('fail');
            }
        };

        $deprecations = $this->captureDeprecations(fn(): array => $this->retriesAfterFailure($handler, 25), $sent);
        self::assertSame([], $sent);
        self::assertCount(1, $deprecations);
    }

    public function testInterfaceHandlerTriggersNoDeprecation(): void
    {
        $logger = new TestLogger();
        $deprecations = $this->captureDeprecations(fn(): array => $this->retriesAfterFailure(new PolicyHandler(true, 3), 0, $logger), $sent);

        self::assertCount(1, $sent);
        self::assertSame([], $deprecations);
        self::assertSame([], array_filter($logger->getLogs(), fn(array $record): bool => $record['level'] === LogLevel::WARNING));
    }

    /**
     * Runs $run and returns the E_USER_DEPRECATED messages it triggered (they are silenced with @).
     *
     * @param Closure(): list<MessageInterface> $run
     * @param list<MessageInterface>|null $sent
     *
     * @param-out list<MessageInterface> $sent
     *
     * @return list<string>
     */
    private function captureDeprecations(Closure $run, ?array &$sent): array
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            if ($level === E_USER_DEPRECATED) {
                $deprecations[] = $message;
            }

            return true;
        });
        try {
            $sent = $run();
        } finally {
            restore_error_handler();
        }

        return $deprecations;
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

final class LegacyOwnMethodsHandler implements HandlerInterface
{
    public int $canRetryCalls = 0;

    public function __construct(private readonly bool $retry, private readonly int $max) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('fail');
    }

    public function canRetry(): bool
    {
        ++$this->canRetryCalls;

        return $this->retry;
    }

    public function maxRetry(): int
    {
        return $this->max;
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
