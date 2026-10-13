<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTime;
use Redis;

/**
 * Class RedisShutdown provides redis implementation of Tomaj\Hermes\Shutdown\ShutdownInterface
 *
 * Set UNIX timestamp (as `string`) to key `$key` (default `hermes_shutdown`) to shutdown Hermes worker.
 */
class RedisShutdown implements ShutdownInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $key = 'hermes_shutdown',
    ) {}

    /**
     * Returns true:
     * - if shutdown timestamp is set,
     * - and timestamp is not in future,
     * - and hermes was started ($startTime) before timestamp
     */
    public function shouldShutdown(DateTime $startTime): bool
    {
        // load UNIX timestamp from redis
        $shutdownTime = $this->redis->get($this->key);
        if (!is_string($shutdownTime)) {
            return false;
        }
        $shutdownTime = (int) $shutdownTime;

        // do not shutdown if shutdown time is in future
        if ($shutdownTime > time()) {
            return false;
        }

        // do not shutdown if hermes started after shutdown time
        if ($shutdownTime < $startTime->getTimestamp()) {
            return false;
        }

        return true;
    }

    /**
     * Sets to Redis value `$shutdownTime` (or current DateTime) to `$key` defined in constructor.
     */
    public function shutdown(?DateTime $shutdownTime = null): bool
    {
        if ($shutdownTime === null) {
            $shutdownTime = new DateTime();
        }

        return $this->redis->set($this->key, $shutdownTime->format('U'));
    }
}
