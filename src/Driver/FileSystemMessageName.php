<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

/**
 * File names of FileSystemDriver messages: "<due time>-<random>.json".
 *
 * The due time is "%017.6F" (seconds with microseconds, zero-padded), so sorting the names sorts the
 * messages by due time; it fits times up to the year 2286. A claimed message in processing/ is named
 * "<claim time>~<name>", so the claim time is set by the same atomic rename that claims the message.
 *
 * @internal
 */
final class FileSystemMessageName
{
    public const string EXTENSION = '.json';

    public static function create(float $due, string $random): string
    {
        return sprintf('%017.6F', max(0.0, $due)) . "-{$random}" . self::EXTENSION;
    }

    public static function isMessage(string $name): bool
    {
        return str_ends_with($name, self::EXTENSION);
    }

    public static function isDue(string $name, float $now): bool
    {
        // a cast reads the leading number and ignores the rest of the name
        return (float) $name <= $now;
    }

    public static function claimed(string $name, int $now): string
    {
        return "{$now}~{$name}";
    }

    public static function claimTime(string $claimed): int
    {
        // a cast reads the leading number and ignores the rest of the name
        return (int) $claimed;
    }

    public static function unclaimed(string $claimed): string
    {
        $separator = strpos($claimed, '~');
        return $separator === false ? $claimed : substr($claimed, $separator + 1);
    }

    /**
     * A claimed message is redelivered when its claim is more than $visibilityTimeout seconds old.
     */
    public static function isExpired(int $claimed, int $now, int $visibilityTimeout): bool
    {
        return $now - $claimed > $visibilityTimeout;
    }
}
