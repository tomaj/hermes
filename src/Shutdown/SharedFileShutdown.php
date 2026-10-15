<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTime;

final readonly class SharedFileShutdown implements ShutdownInterface
{
    public function __construct(
        private string $filePath,
    ) {}

    #[\Override]
    public function shouldShutdown(DateTime $startTime): bool
    {
        clearstatcache(false, $this->filePath);

        if (!file_exists($this->filePath)) {
            return false;
        }

        $time = filemtime($this->filePath);
        if ($time !== false && $time >= $startTime->getTimestamp()) {
            return true;
        }

        return false;
    }

    /**
     * Creates file defined in constructor with modification time `$shutdownTime` (or current DateTime).
     */
    #[\Override]
    public function shutdown(?DateTime $shutdownTime = null): bool
    {
        $shutdownTime ??= new DateTime();

        return touch($this->filePath, (int) $shutdownTime->format('U'));
    }
}
