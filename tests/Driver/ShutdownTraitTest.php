<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use DateTime;

/**
 * @covers \Tomaj\Hermes\Driver\ShutdownTrait
 */
class ShutdownTraitTest extends TestCase
{
    public function testSetShutdown(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        
        $testClass = new class {
            use ShutdownTrait;
            
            public function callShouldShutdown(): bool
            {
                return $this->shouldShutdown();
            }
            
            public function callCheckShutdown(): void
            {
                $this->checkShutdown();
            }
        };
        
        $testClass->setShutdown($shutdown);
        
        // The method itself doesn't return anything, so we just verify it doesn't throw
        $this->assertTrue(true);
    }
    
    public function testShouldShutdownWhenNotSet(): void
    {
        $testClass = new class {
            use ShutdownTrait;
            
            public function callShouldShutdown(): bool
            {
                return $this->shouldShutdown();
            }
        };
        
        // When shutdown is not set, shouldShutdown should return false
        $this->assertFalse($testClass->callShouldShutdown());
    }
    
    public function testShouldShutdownWhenSetAndReturnsFalse(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects($this->once())
                 ->method('shouldShutdown')
                 ->willReturn(false);
        
        $testClass = new class {
            use ShutdownTrait;
            
            public function callShouldShutdown(): bool
            {
                return $this->shouldShutdown();
            }
        };
        
        $testClass->setShutdown($shutdown);
        $this->assertFalse($testClass->callShouldShutdown());
    }
    
    public function testShouldShutdownWhenSetAndReturnsTrue(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects($this->once())
                 ->method('shouldShutdown')
                 ->willReturn(true);
        
        $testClass = new class {
            use ShutdownTrait;
            
            public function callShouldShutdown(): bool
            {
                return $this->shouldShutdown();
            }
        };
        
        $testClass->setShutdown($shutdown);
        $this->assertTrue($testClass->callShouldShutdown());
    }
    
    public function testCheckShutdownWhenNoShutdownSet(): void
    {
        $testClass = new class {
            use ShutdownTrait;
            
            public function callCheckShutdown(): void
            {
                $this->checkShutdown();
            }
        };
        
        // Should not throw when shutdown is not set
        $testClass->callCheckShutdown();
        $this->assertTrue(true);
    }
    
    public function testCheckShutdownWhenShouldShutdownReturnsFalse(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects($this->once())
                 ->method('shouldShutdown')
                 ->willReturn(false);
        
        $testClass = new class {
            use ShutdownTrait;
            
            public function callCheckShutdown(): void
            {
                $this->checkShutdown();
            }
        };
        
        $testClass->setShutdown($shutdown);
        
        // Should not throw when shouldShutdown returns false
        $testClass->callCheckShutdown();
        $this->assertTrue(true);
    }
    
    public function testCheckShutdownWhenShouldShutdownReturnsTrue(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects($this->once())
                 ->method('shouldShutdown')
                 ->willReturn(true);
        
        $testClass = new class {
            use ShutdownTrait;
            
            public function callCheckShutdown(): void
            {
                $this->checkShutdown();
            }
        };
        
        $testClass->setShutdown($shutdown);
        
        $this->expectException(ShutdownException::class);
        $testClass->callCheckShutdown();
    }
}
