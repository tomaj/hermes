<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

/**
 * @covers \Tomaj\Hermes\Handler\RetryTrait
 */
class RetryTraitTest extends TestCase
{
    public function testCanRetry(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };

        self::assertTrue($handler->canRetry());
    }

    public function testMaxRetry(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };

        self::assertSame(25, $handler->maxRetry());
    }
}
