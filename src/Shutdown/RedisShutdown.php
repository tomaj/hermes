<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTimeImmutable;
use DateTimeInterface;
use Redis;

/**
 * Class RedisShutdown provides redis implementation of Tomaj\Hermes\Shutdown\ShutdownInterface
 *
 * Set the shutdown time (`"seconds.microseconds"`, a whole UNIX timestamp also works) to key `$key` (default `hermes_shutdown`) to shutdown Hermes worker.
 */
final readonly class RedisShutdown implements ShutdownInterface
{
    public function __construct(
        private Redis $redis,
        private string $key = 'hermes_shutdown',
    ) {}

    /**
     * Returns true when the stored shutdown time is not in the future
     * and is later than the moment this worker started ($startTime).
     */
    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        // a missing key reads as false (phpredis) or null (Predis), which parse() turns into null
        $shutdownTime = ShutdownTime::parse($this->redis->get($this->key));

        return $shutdownTime !== null && ShutdownTime::isRequested($shutdownTime, $startTime, microtime(true));
    }

    /**
     * Sets to Redis value `$shutdownTime` (or the current time) to `$key` defined in constructor.
     */
    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        $shutdownTime ??= new DateTimeImmutable();

        return $this->redis->set($this->key, ShutdownTime::format($shutdownTime));
    }
}
