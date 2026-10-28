<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;

/**
 * Priorities from argv or console options arrive as strings; the drivers compare them strictly.
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class HandlePrioritiesTest extends TestCase
{
    public function testNumericStringPrioritiesAreCastToInt(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::once())->method('wait')->with(self::anything(), [110, 100, 50, -5]);

        new Dispatcher($driver)->handle(['110', 100, '050', '-5']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPriorities(): iterable
    {
        yield 'word' => ['high'];
        yield 'empty' => [''];
        yield 'decimal' => ['1.9'];
    }

    #[DataProvider('invalidPriorities')]
    public function testNonIntegerStringPriorityIsRejected(string $priority): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::never())->method('wait');

        try {
            new Dispatcher($driver)->handle([$priority]);
            self::fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException $e) {
            self::assertSame("Priority must be an integer, '{$priority}' given", $e->getMessage());
        }
    }

    public function testNoPrioritiesStayEmpty(): void
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects(self::once())->method('wait')->with(self::anything(), []);

        new Dispatcher($driver)->handle();
    }
}
