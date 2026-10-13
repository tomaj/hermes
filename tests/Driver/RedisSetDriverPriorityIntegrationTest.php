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

/**
 * Runs against a real Redis server. Set HERMES_TEST_REDIS (for example
 * tcp://127.0.0.1:6379) to run it; without it the tests are skipped.
 */
#[CoversClass(RedisSetDriver::class)]
#[RequiresPhpExtension('redis')]
class RedisSetDriverPriorityIntegrationTest extends TestCase
{
    private Redis $redis;

    private string $prefix;

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

    protected function tearDown(): void
    {
        if (isset($this->prefix)) {
            $this->redis->del($this->prefix, $this->prefix . '_higher', $this->prefix . '_top', $this->prefix . '_lower', $this->prefix . '_schedule');
        }
    }

    public function testPrioritiesAreTakenInNumericOrderRegardlessOfSetupOrder(): void
    {
        $driver = new RedisSetDriver($this->redis, $this->prefix, 0, $this->prefix . '_schedule');
        $driver->setupPriorityQueue($this->prefix . '_higher', 200);
        $driver->setupPriorityQueue($this->prefix . '_top', 1000);
        $driver->setupPriorityQueue($this->prefix . '_lower', 50);
        foreach ([50, 100, 200, 1000] as $priority) {
            $driver->send(new Message('job', []), $priority);
        }

        $priorities = [];
        $driver->setMaxProcessItems(4);
        $driver->wait(static function (MessageInterface $message, int $priority) use (&$priorities): void {
            $priorities[] = $priority;
        });

        self::assertSame([1000, 200, 100, 50], $priorities);
    }
}
