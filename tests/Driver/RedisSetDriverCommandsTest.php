<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;

/**
 * The exact Redis commands RedisSetDriver sends.
 *
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @requires extension redis
 */
class RedisSetDriverCommandsTest extends TestCase
{
    public function testMessageWithFutureExecuteAtIsScheduled(): void
    {
        $executeAt = microtime(true) + 3600;
        $message = new Message('type', [], 'id-1', null, $executeAt);
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('zAdd')
            ->with('hermes_schedule', $executeAt, new MessageSerializer()->serialize($message));
        $redis->expects(self::never())->method('sAdd');

        self::assertTrue(new RedisSetDriver($redis)->send($message));
    }
}
