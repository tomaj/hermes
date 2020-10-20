<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Restart;

use DateTime;

class SharedFileRestart implements RestartInterface
{
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldRestart(DateTime $startTime): bool
    {
        if (!file_exists($this->filePath)) {
            return false;
        }

        $time = filemtime($this->filePath);
        if ($time >= $startTime->getTimestamp()) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Creates file defined in constructor with modification time `$restartTime` (or current DateTime).
     */
    public function restart(DateTime $restartTime = null): bool
    {
        if ($restartTime === null) {
            $restartTime = new DateTime();
        }

        return touch($this->filePath, (int) $restartTime->format('U'));
    }
}
