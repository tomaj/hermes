<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTime;

class SharedFileShutdown implements ShutdownInterface
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * {@inheritdoc}
     */
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
     * {@inheritdoc}
     *
     * Creates file defined in constructor with modification time `$shutdownTime` (or current DateTime).
     */
    public function shutdown(?DateTime $shutdownTime = null): bool
    {
        if ($shutdownTime === null) {
            $shutdownTime = new DateTime();
        }

        return touch($this->filePath, (int) $shutdownTime->format('U'));
    }
}
