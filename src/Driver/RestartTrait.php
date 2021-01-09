<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use DateTime;
use Tomaj\Hermes\Restart\RestartException;
use Tomaj\Hermes\Restart\RestartInterface;

trait RestartTrait
{
    /** @var RestartInterface */
    private $restart;

    /** @var DateTime */
    private $startTime;

    public function setRestart(RestartInterface $restart)
    {
        $this->restart = $restart;
        $this->startTime = new DateTime();
    }

    private function shouldRestart(): bool
    {
        return $this->restart !== null && $this->restart->shouldRestart($this->startTime);
    }

    /**
     * @throws RestartException
     */
    private function checkRestart(): void
    {
        if ($this->shouldRestart()) {
            throw new RestartException();
        }
    }
}
