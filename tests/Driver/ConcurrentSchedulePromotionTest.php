<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Test\IntegrationServices;

/**
 * Several worker processes promote due delayed messages from one schedule at
 * the same time. Every message must be handled exactly once. Runs against a
 * real Redis server when HERMES_TEST_REDIS is set.
 */
#[CoversClass(PredisSetDriver::class)]
#[CoversClass(RedisSetDriver::class)]
class ConcurrentSchedulePromotionTest extends TestCase
{
    private const int MESSAGES = 200;

    private const int WORKERS = 4;

    private Client $redis;

    private string $url;

    private string $prefix;

    #[\Override]
    protected function setUp(): void
    {
        $this->url = IntegrationServices::get('HERMES_TEST_REDIS');
        $this->redis = new Client($this->url);
        try {
            $this->redis->ping();
        } catch (ConnectionException $e) {
            IntegrationServices::unavailable("Redis is not reachable at {$this->url}: {$e->getMessage()}");
        }
        $this->prefix = 'hermes_test_' . bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->prefix)) {
            $this->redis->del([$this->prefix, $this->prefix . '_schedule']);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function drivers(): iterable
    {
        yield 'phpredis' => ['redis'];
        yield 'predis' => ['predis'];
    }

    #[DataProvider('drivers')]
    public function testEveryDueMessageIsDeliveredOnceAcrossWorkers(string $driverName): void
    {
        if ($driverName === 'redis' && !extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not loaded');
        }

        $driver = new PredisSetDriver($this->redis, $this->prefix, 0, $this->prefix . '_schedule');
        $executeAt = microtime(true) + 0.5;
        $expected = [];
        for ($i = 0; $i < self::MESSAGES; $i++) {
            $expected[] = "m{$i}";
            $driver->send(new Message('type', [], "m{$i}", null, $executeAt));
        }
        self::assertSame(self::MESSAGES, $this->redis->zcard($this->prefix . '_schedule'));
        usleep(600_000);

        $processes = [];
        $pipes = [];
        for ($w = 0; $w < self::WORKERS; $w++) {
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/Fixture/schedule-worker.php', $driverName, $this->url, $this->prefix],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $workerPipes,
            );
            self::assertIsResource($process);
            $processes[] = $process;
            $pipes[] = $workerPipes;
        }

        $handled = [];
        foreach ($processes as $w => $process) {
            $output = (string) stream_get_contents($pipes[$w][1]);
            $errors = (string) stream_get_contents($pipes[$w][2]);
            fclose($pipes[$w][1]);
            fclose($pipes[$w][2]);
            self::assertSame(0, proc_close($process), $errors);
            self::assertSame('', $errors);
            foreach (array_filter(explode("\n", trim($output)), static fn(string $id): bool => $id !== '') as $id) {
                $handled[] = $id;
            }
        }

        sort($handled);
        sort($expected);
        self::assertSame($expected, $handled);
    }
}
