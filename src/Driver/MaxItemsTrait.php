<?php

namespace Tomaj\Hermes\Driver;

trait MaxItemsTrait
{
    /**
     * @var integer
     */
    private $processed = 0;

    /**
     * @var integer
     */
    private $maxProcessItems = 0;

    public function setMaxProcessItems(int $count): void
    {
        $this->maxProcessItems = $count;
    }

    public function incrementProcessedItems(): int
    {
        $this->processed++;
        return $this->processed;
    }

    public function processed(): int
    {
        return $this->processed;
    }

    public function shouldProcessNext(): bool
    {
        if ($this->maxProcessItems == 0) {
            return true;
        }
        if ($this->processed >= $this->maxProcessItems) {
            return false;
        }
        return true;
    }
}
