<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTime;
use InvalidArgumentException;

/**
 * Class RedisShutdown provides redis implementation of Tomaj\Hermes\Shutdown\ShutdownInterface
 *
 * Set UNIX timestamp (as `string`) to key `$key` (default `hermes_shutdown`) to shutdown Hermes workoer.
 */
class RedisShutdown implements ShutdownInterface
{
    /** @var string */
    private $key;

    /** @var \Predis\Client|\Redis */
    private $redis;

    public function __construct($redis, string $key = 'hermes_shutdown')
    {
        if (!(($redis instanceof \Predis\Client) || ($redis instanceof \Redis))) {
            throw new InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->key = $key;
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     *
     * Returns true:
     *
     * - if shutdown timestamp is set,
     * - and timestamp is not in future,
     * - and hermes was started ($startTime) before timestamp
     */
    public function shouldShutdown(DateTime $startTime): bool
    {
        // load UNIX timestamp from redis
        $shutdownTime = $this->redis->get($this->key);
        if ($shutdownTime === null) {
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
     * {@inheritdoc}
     *
     * Sets to Redis value `$shutdownTime` (or current DateTime) to `$key` defined in constructor.
     */
    public function shutdown(DateTime $shutdownTime = null): bool
    {
        if ($shutdownTime === null) {
            $shutdownTime = new DateTime();
        }

        $response = $this->redis->set($this->key, $shutdownTime->format('U'));

        if ($this->redis instanceof \Redis) {
            // \Redis::set() returns TRUE/FALSE
            return $response;
        }

        if ($this->redis instanceof \Predis\Client) {
            /** @var \Predis\Response\Status $response */
            return $response->getPayload() === 'OK';
        }
    }
}
