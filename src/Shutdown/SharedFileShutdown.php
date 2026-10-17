<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Stops workers through a file. shutdown() writes the shutdown time ("seconds.microseconds")
 * into the file and sets its modification time to that second. When the file holds no time, or
 * was touched later (for example with `touch`), its modification time is used instead.
 */
final readonly class SharedFileShutdown implements ShutdownInterface
{
    public function __construct(
        private string $filePath,
    ) {}

    /**
     * Returns true when the file holds a shutdown time that is not in the future
     * and is later than the moment this worker started ($startTime).
     */
    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        clearstatcache(false, $this->filePath);

        // a missing file means keep running
        $modified = @filemtime($this->filePath);
        if ($modified === false) {
            return false;
        }

        // The time in the file is only current while the file was not touched since: then it falls
        // in the second of the modification time. Otherwise (`touch`, or no time in it) use that second.
        $shutdownTime = ShutdownTime::parse(@file_get_contents($this->filePath));
        if ($shutdownTime === null || (int) floor($shutdownTime) !== $modified) {
            $shutdownTime = $modified;
        }

        return ShutdownTime::isRequested($shutdownTime, $startTime, microtime(true));
    }

    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        $shutdownTime ??= new DateTimeImmutable();

        // Rewrite the file in place, like `touch` in 5.x: an existing file keeps its owner and mode, and
        // the directory does not have to be writable. A worker that reads the file during the write sees
        // an empty or shorter time and at worst stops on its next check instead of this one.
        if (@file_put_contents($this->filePath, ShutdownTime::format($shutdownTime), LOCK_EX) === false) {
            return false;
        }

        return touch($this->filePath, (int) $shutdownTime->format('U'));
    }
}
