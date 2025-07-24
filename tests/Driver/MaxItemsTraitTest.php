<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\MaxItemsTrait;

/**
 * @covers \Tomaj\Hermes\Driver\MaxItemsTrait
 */
class MaxItemsTraitTest extends TestCase
{
    public function testMaxItemsTraitMethods(): void
    {
        $handler = new class {
            use MaxItemsTrait;
        };
        
        // Test initial state
        $this->assertEquals(0, $handler->processed());
        $this->assertTrue($handler->shouldProcessNext());
        
        // Test incrementing
        $this->assertEquals(1, $handler->incrementProcessedItems());
        $this->assertEquals(1, $handler->processed());
        
        // Test with max items set
        $handler->setMaxProcessItems(2);
        $this->assertTrue($handler->shouldProcessNext());
        
        $handler->incrementProcessedItems(); // Now at 2
        $this->assertFalse($handler->shouldProcessNext());
    }
    
    public function testMaxItemsTraitWithZeroMaxItems(): void
    {
        $handler = new class {
            use MaxItemsTrait;
        };
        
        $handler->setMaxProcessItems(0);
        
        // With max items = 0, should always return true
        $this->assertTrue($handler->shouldProcessNext());
        
        $handler->incrementProcessedItems();
        $handler->incrementProcessedItems();
        $handler->incrementProcessedItems();
        
        $this->assertTrue($handler->shouldProcessNext());
    }
}
