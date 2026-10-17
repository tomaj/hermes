<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTime;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Redis;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

/**
 * A restart right after a shutdown, against a real Redis, with both clients (#115).
 *
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 * @covers \Tomaj\Hermes\Shutdown\ShutdownTime
 */
class RedisShutdownIntegrationTest extends TestCase
{
    private string $key;

    private string $host = '127.0.0.1';

    private int $port = 6379;

    #[\Override]
    protected function setUp(): void
    {
        $url = getenv('HERMES_TEST_REDIS');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('Set HERMES_TEST_REDIS to run the Redis integration tests.');
        }
        $parts = parse_url($url);
        $this->host = is_array($parts) && isset($parts['host']) ? $parts['host'] : '127.0.0.1';
        $this->port = is_array($parts) && isset($parts['port']) ? $parts['port'] : 6379;
        $this->key = 'hermes_test_shutdown_' . bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->key)) {
            new Client(['host' => $this->host, 'port' => $this->port])->del([$this->key]);
        }
    }

    private function assertRestartRightAfterShutdownKeepsRunning(ShutdownInterface $shutdown): void
    {
        $oldWorkerStart = new DateTime();
        usleep(1000);
        self::assertTrue($shutdown->shutdown());
        usleep(1000);
        $newWorkerStart = new DateTime();

        self::assertTrue($shutdown->shouldShutdown($oldWorkerStart));
        self::assertFalse($shutdown->shouldShutdown($newWorkerStart));
    }

    /**
     * @requires extension redis
     */
    public function testPhpredis(): void
    {
        $redis = new Redis();
        $redis->connect($this->host, $this->port);

        $this->assertRestartRightAfterShutdownKeepsRunning(new RedisShutdown($redis, $this->key));
        $stored = $redis->get($this->key);
        self::assertIsString($stored);
        self::assertMatchesRegularExpression('/^\d+\.\d{6}$/', $stored);
    }

    public function testPredis(): void
    {
        $redis = new Client(['host' => $this->host, 'port' => $this->port]);

        $this->assertRestartRightAfterShutdownKeepsRunning(new PredisShutdown($redis, $this->key));
    }
}
