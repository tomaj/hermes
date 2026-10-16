<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTime;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

/**
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class SharedFileShutdownTest extends TestCase
{
    private string $tempFile;

    #[\Override]
    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hermes_test_shutdown_');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testShouldShutdownWhenFileDoesNotExist(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $shutdown = new SharedFileShutdown($this->tempFile);
        $startTime = new DateTime();

        self::assertFalse($shutdown->shouldShutdown($startTime));
    }

    public function testShouldShutdownWhenFileIsOlder(): void
    {
        // Create file first
        touch($this->tempFile, time() - 3600); // 1 hour ago

        $shutdown = new SharedFileShutdown($this->tempFile);
        $startTime = new DateTime(); // now

        self::assertFalse($shutdown->shouldShutdown($startTime));
    }

    public function testShouldShutdownWhenFileIsNewer(): void
    {
        $startTime = new DateTime();
        sleep(1); // Wait a bit to ensure file is newer

        // Create file after start time
        touch($this->tempFile);

        $shutdown = new SharedFileShutdown($this->tempFile);

        self::assertTrue($shutdown->shouldShutdown($startTime));
    }

    public function testShouldShutdownWhenTheFileTimeEqualsTheStartTime(): void
    {
        touch($this->tempFile, 1700000000);
        clearstatcache();

        $shutdown = new SharedFileShutdown($this->tempFile);

        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000000')));
    }

    public function testShutdownCreatesFile(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $shutdown = new SharedFileShutdown($this->tempFile);
        $result = $shutdown->shutdown();

        self::assertTrue($result);
        self::assertTrue(file_exists($this->tempFile));
    }

    public function testShutdownWithSpecificTime(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $shutdown = new SharedFileShutdown($this->tempFile);
        $shutdownTime = new DateTime('2023-01-01 12:00:00');
        $result = $shutdown->shutdown($shutdownTime);

        self::assertTrue($result);
        self::assertTrue(file_exists($this->tempFile));
        self::assertSame($shutdownTime->getTimestamp(), filemtime($this->tempFile));
    }

    public function testShutdownWithNullTime(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $shutdown = new SharedFileShutdown($this->tempFile);
        $beforeTime = time();
        $result = $shutdown->shutdown();
        $afterTime = time();

        self::assertTrue($result);
        self::assertTrue(file_exists($this->tempFile));

        $fileTime = filemtime($this->tempFile);
        self::assertGreaterThanOrEqual($beforeTime, $fileTime);
        self::assertLessThanOrEqual($afterTime, $fileTime);
    }
}
