<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

/**
 * @covers \Tomaj\Hermes\Handler\RetryTrait
 */
class RetryTraitTest extends \PHPUnit\Framework\TestCase
{
    public function testCanRetry(): void
    {
        $handler = new class implements \Tomaj\Hermes\Handler\HandlerInterface {
            use \Tomaj\Hermes\Handler\RetryTrait;
            
            public function handle(\Tomaj\Hermes\MessageInterface $message): bool
            {
                return true;
            }
        };
        
        $this->assertTrue($handler->canRetry());
    }
    
    public function testMaxRetry(): void
    {
        $handler = new class implements \Tomaj\Hermes\Handler\HandlerInterface {
            use \Tomaj\Hermes\Handler\RetryTrait;
            
            public function handle(\Tomaj\Hermes\MessageInterface $message): bool
            {
                return true;
            }
        };
        
        $this->assertEquals(25, $handler->maxRetry());
    }
}
