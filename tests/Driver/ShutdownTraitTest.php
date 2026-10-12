<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\ShutdownTrait;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

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
        self::expectNotToPerformAssertions();
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
        self::assertFalse($testClass->callShouldShutdown());
    }

    public function testShouldShutdownWhenSetAndReturnsFalse(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects(self::once())
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
        self::assertFalse($testClass->callShouldShutdown());
    }

    public function testShouldShutdownWhenSetAndReturnsTrue(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects(self::once())
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
        self::assertTrue($testClass->callShouldShutdown());
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

        self::expectNotToPerformAssertions();
        $testClass->callCheckShutdown();
    }

    public function testCheckShutdownWhenShouldShutdownReturnsFalse(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects(self::once())
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
        $testClass->callCheckShutdown();
    }

    public function testCheckShutdownWhenShouldShutdownReturnsTrue(): void
    {
        $shutdown = $this->createMock(ShutdownInterface::class);
        $shutdown->expects(self::once())
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
