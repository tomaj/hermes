<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTimeInterface;

/**
 * Shared comparison for the built-in shutdowns.
 *
 * Shutdown times are stored as "seconds.microseconds" ("1700000000.123456"). A whole-second
 * value, written by Hermes 5.x or by `touch`, counts as the start of that second.
 *
 * @internal
 */
final class ShutdownTime
{
    public static function format(DateTimeInterface $time): string
    {
        return $time->format('U.u');
    }

    public static function parse(mixed $value): ?float
    {
        return is_string($value) && is_numeric($value) ? (float) $value : null;
    }

    /**
     * True when the shutdown was requested after the worker started and is not in the future.
     */
    public static function isRequested(float $shutdownTime, DateTimeInterface $startTime, float $now): bool
    {
        return $shutdownTime <= $now && $shutdownTime > $startTime->format('U.u');
    }
}
