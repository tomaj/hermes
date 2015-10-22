<?php

namespace Tomaj\Hermes\Driver;

trait MaxItemsTrait
{
    private $processed = 0;

    private $maxProcessItems = 0;

    public function setMaxProcessItems($count)
    {
        $this->maxProcessItems = $count;
    }

    public function incrementProcessedItems()
    {
        $this->processed++;
    }

    public function processed()
    {
        return $this->processed;
    }

    public function shouldProcessNext()
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
