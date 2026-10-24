<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

/**
 * The shutdown file is touched by another process, which PHP's stat cache does not notice.
 *
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class SharedFileShutdownStatCacheTest extends TestCase
{
    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/hermes_statcache_' . bin2hex(random_bytes(4));
        touch($this->file, time() - 3600);
    }

    #[\Override]
    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testATouchFromAnotherProcessIsSeen(): void
    {
        $shutdown = new SharedFileShutdown($this->file);
        $startTime = new DateTimeImmutable('-60 seconds');

        // the file is older than the worker: keep running, and PHP caches the stat
        self::assertFalse($shutdown->shouldShutdown($startTime));

        $command = 'touch ' . escapeshellarg($this->file);
        exec($command, $output, $exitCode);
        self::assertSame(0, $exitCode);

        self::assertTrue($shutdown->shouldShutdown($startTime));
    }
}
