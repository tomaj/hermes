<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

trait RetryTrait
{
    public function canRetry(): bool
    {
        return true;
    }

    public function maxRetry(): int
    {
        return 25;
    }
}
