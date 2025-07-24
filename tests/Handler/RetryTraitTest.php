<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
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
            
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };
        
        $this->assertTrue($handler->canRetry());
    }
    
    public function testMaxRetry(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;
            
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };
        
        $this->assertEquals(25, $handler->maxRetry());
    }
}
