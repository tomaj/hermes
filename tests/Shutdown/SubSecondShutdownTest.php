<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Shutdown\ShutdownTime;
use Tomaj\Hermes\Test\Double\PredisClientDouble;

/**
 * Shutdown times keep microseconds, so a worker started in the same second as the
 * shutdown request is only stopped when it started before the request (#115).
 *
 * @covers \Tomaj\Hermes\Shutdown\ShutdownTime
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class SubSecondShutdownTest extends TestCase
{
    private string $directory;

    private string $file;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/hermes_subsecond_' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        $this->file = $this->directory . '/shutdown';
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->entries() as $entry) {
            unlink($this->directory . '/' . $entry);
        }
        rmdir($this->directory);
    }

    /**
     * @return list<string>
     */
    private function entries(): array
    {
        $entries = scandir($this->directory);
        self::assertIsArray($entries);

        return array_values(array_diff($entries, ['.', '..']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function storages(): iterable
    {
        yield 'phpredis' => ['redis'];
        yield 'predis' => ['predis'];
        yield 'file' => ['file'];
    }

    private function shutdownStoring(string $storage, string $value): ShutdownInterface
    {
        switch ($storage) {
            case 'redis':
                if (!extension_loaded('redis')) {
                    self::markTestSkipped('The redis extension is not loaded.');
                }
                $redis = self::createStub(Redis::class);
                $redis->method('get')->willReturn($value);
                return new RedisShutdown($redis);
            case 'predis':
                $predis = self::createStub(PredisClientDouble::class);
                $predis->method('get')->willReturn($value);
                return new PredisShutdown($predis);
            default:
                file_put_contents($this->file, $value);
                // as written by shutdown(): the modification time is the second of the stored time
                touch($this->file, is_numeric($value) ? (int) floor((float) $value) : 1700000000);
                return new SharedFileShutdown($this->file);
        }
    }

    #[DataProvider('storages')]
    public function testWorkerStartedLaterInTheSameSecondKeepsRunning(string $storage): void
    {
        $shutdown = $this->shutdownStoring($storage, '1700000000.400000');

        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000.700000')));
    }

    #[DataProvider('storages')]
    public function testWorkerStartedEarlierInTheSameSecondStops(string $storage): void
    {
        $shutdown = $this->shutdownStoring($storage, '1700000000.400000');

        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000000.100000')));
    }

    #[DataProvider('storages')]
    public function testWholeSecondValueCountsAsTheStartOfThatSecond(string $storage): void
    {
        $shutdown = $this->shutdownStoring($storage, '1700000000');

        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000.000000')));
        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000.500000')));
        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1699999999.900000')));
    }

    #[DataProvider('storages')]
    public function testFutureShutdownTimeDoesNotStopAnyoneYet(string $storage): void
    {
        $shutdown = $this->shutdownStoring($storage, sprintf('%.6F', microtime(true) + 3600));

        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000')));
    }

    #[DataProvider('storages')]
    public function testUnreadableValueIsIgnored(string $storage): void
    {
        if ($storage === 'file') {
            // A file without a time falls back to its modification time.
            file_put_contents($this->file, 'restart please');
            touch($this->file, 1700000010);
            $shutdown = new SharedFileShutdown($this->file);
            self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000005')));
            self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000010.5')));
            return;
        }

        $shutdown = $this->shutdownStoring($storage, 'restart please');
        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1600000000')));
    }

    public function testTouchAfterAShutdownStillCounts(): void
    {
        $shutdown = new SharedFileShutdown($this->file);
        self::assertTrue($shutdown->shutdown(new DateTime('@1700000000.900000')));

        // An operator touches the file later instead of calling shutdown() again.
        touch($this->file, 1700000100);

        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000050')));
        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000100.1')));
    }

    public function testFileShutdownWritesMicroseconds(): void
    {
        $shutdown = new SharedFileShutdown($this->file);

        self::assertTrue($shutdown->shutdown(new DateTime('@1700000000.123456')));

        self::assertSame('1700000000.123456', file_get_contents($this->file));
        clearstatcache();
        self::assertSame(1700000000, filemtime($this->file));
        self::assertSame(['shutdown'], $this->entries());
        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000000.123455')));
        self::assertFalse($shutdown->shouldShutdown(new DateTime('@1700000000.123456')));
    }

    public function testFileShutdownRewritesAnExistingFileInPlace(): void
    {
        // As with `touch` in 5.x, an existing file is enough: the directory may be read-only,
        // and the file keeps its inode and mode.
        file_put_contents($this->file, '1600000000.000000-and-longer-than-the-new-time');
        chmod($this->file, 0o660);
        clearstatcache();
        $inode = fileinode($this->file);
        chmod($this->directory, 0o555);
        try {
            self::assertTrue(new SharedFileShutdown($this->file)->shutdown(new DateTime('@1700000000.5')));
        } finally {
            chmod($this->directory, 0o755);
        }

        clearstatcache();
        self::assertSame('1700000000.500000', file_get_contents($this->file));
        self::assertSame($inode, fileinode($this->file));
        self::assertSame(0o660, fileperms($this->file) & 0o777);
    }

    public function testFileShutdownReportsFailure(): void
    {
        self::assertFalse(new SharedFileShutdown($this->directory . '/missing/shutdown')->shutdown());

        mkdir($this->file);
        self::assertFalse(new SharedFileShutdown($this->file)->shutdown());
        rmdir($this->file);
        self::assertSame([], $this->entries());
    }

    public function testRestartRightAfterAShutdownIsNotStopped(): void
    {
        $shutdown = new SharedFileShutdown($this->file);
        $oldWorkerStart = new DateTime();
        usleep(1000);
        self::assertTrue($shutdown->shutdown());
        usleep(1000);
        $newWorkerStart = new DateTime();

        self::assertTrue($shutdown->shouldShutdown($oldWorkerStart));
        self::assertFalse($shutdown->shouldShutdown($newWorkerStart));
    }

    public function testTimeLateInTheSecondIsReadFromTheFile(): void
    {
        $shutdown = new SharedFileShutdown($this->file);
        self::assertTrue($shutdown->shutdown(new DateTime('@1700000000.900000')));

        self::assertTrue($shutdown->shouldShutdown(new DateTime('@1700000000.500000')));
    }

    public function testIsRequestedBoundaries(): void
    {
        $start = new DateTime('@1700000000.500000');

        self::assertTrue(ShutdownTime::isRequested(1700000000.6, $start, 1700000000.6));
        self::assertFalse(ShutdownTime::isRequested(1700000000.6, $start, 1700000000.599));
        self::assertFalse(ShutdownTime::isRequested(1700000000.5, $start, 1700000001.0));
        self::assertTrue(ShutdownTime::isRequested(1700000000.500001, $start, 1700000001.0));
    }

    public function testFormatKeepsMicroseconds(): void
    {
        self::assertSame('1700000000.000001', ShutdownTime::format(new DateTime('@1700000000.000001')));
    }

    public function testParse(): void
    {
        self::assertSame(1700000000.25, ShutdownTime::parse('1700000000.25'));
        self::assertSame(1700000000.0, ShutdownTime::parse('1700000000'));
        self::assertNull(ShutdownTime::parse('soon'));
        self::assertNull(ShutdownTime::parse(''));
        self::assertNull(ShutdownTime::parse(false));
        self::assertNull(ShutdownTime::parse(null));
        self::assertNull(ShutdownTime::parse(1700000000));
    }
}
