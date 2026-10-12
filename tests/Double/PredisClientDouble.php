<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Double;

use Predis\Client;

/**
 * Predis commands are magic methods. PHPUnit can only mock methods that exist on the class.
 */
class PredisClientDouble extends Client
{
    public function get(mixed ...$arguments): mixed
    {
        return null;
    }

    public function set(mixed ...$arguments): mixed
    {
        return null;
    }

    public function sadd(mixed ...$arguments): mixed
    {
        return null;
    }

    public function zadd(mixed ...$arguments): mixed
    {
        return null;
    }

    public function zrem(mixed ...$arguments): mixed
    {
        return null;
    }

    public function zrangebyscore(mixed ...$arguments): mixed
    {
        return null;
    }

    public function spop(mixed ...$arguments): mixed
    {
        return null;
    }
}
