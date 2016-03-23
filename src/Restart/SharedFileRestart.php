<?php

namespace Tomaj\Hermes\Restart;

use DateTime;

class SharedFileRestart implements RestartInterface
{
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldRestart(DateTime $startTime)
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
}
