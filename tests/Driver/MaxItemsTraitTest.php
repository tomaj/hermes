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
        self::assertEquals(0, $handler->processed());
        self::assertTrue($handler->shouldProcessNext());

        // Test incrementing
        self::assertEquals(1, $handler->incrementProcessedItems());
        self::assertEquals(1, $handler->processed());

        // Test with max items set
        $handler->setMaxProcessItems(2);
        self::assertTrue($handler->shouldProcessNext());

        $handler->incrementProcessedItems(); // Now at 2
        self::assertFalse($handler->shouldProcessNext());
    }

    public function testMaxItemsTraitWithZeroMaxItems(): void
    {
        $handler = new class {
            use MaxItemsTrait;
        };

        $handler->setMaxProcessItems(0);

        // With max items = 0, should always return true
        self::assertTrue($handler->shouldProcessNext());

        $handler->incrementProcessedItems();
        $handler->incrementProcessedItems();
        $handler->incrementProcessedItems();

        self::assertTrue($handler->shouldProcessNext());
    }
}
