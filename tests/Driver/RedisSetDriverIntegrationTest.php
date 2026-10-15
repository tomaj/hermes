<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * Runs against a real Redis server. Set HERMES_TEST_REDIS (for example
 * tcp://127.0.0.1:6379) to run it; without it the tests are skipped.
 */
#[CoversClass(RedisSetDriver::class)]
#[RequiresPhpExtension('redis')]
class RedisSetDriverIntegrationTest extends TestCase
{
    private Redis $redis;

    private string $prefix;

    #[\Override]
    protected function setUp(): void
    {
        $url = getenv('HERMES_TEST_REDIS');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('Set HERMES_TEST_REDIS to run the Redis integration tests.');
        }
        $parts = parse_url($url);
        $this->redis = new Redis();
        $this->redis->connect($parts['host'] ?? '127.0.0.1', $parts['port'] ?? 6379);
        $this->prefix = 'hermes_test_' . bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->prefix)) {
            $this->redis->del($this->prefix, $this->prefix . '_schedule');
        }
    }

    public function testDelayedMessageIsDelivered(): void
    {
        $driver = new RedisSetDriver($this->redis, $this->prefix, 0, $this->prefix . '_schedule');
        $driver->send(new Message('delayed', [], 'delayed-1', null, microtime(true) + 0.3));
        self::assertSame(1, $this->redis->zCard($this->prefix . '_schedule'));

        usleep(400000);
        $driver->setMaxProcessItems(1);
        $ids = [];
        $driver->setShutdown(new PollBudgetShutdown());
        $driver->wait(static function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });

        self::assertSame(['delayed-1'], $ids);
        self::assertSame(0, $this->redis->zCard($this->prefix . '_schedule'));
    }
}
