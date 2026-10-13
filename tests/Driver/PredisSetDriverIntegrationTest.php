<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;

/**
 * Runs against a real Redis server. Set HERMES_TEST_REDIS (for example
 * tcp://127.0.0.1:6379) to run it; without it the tests are skipped.
 */
#[CoversClass(PredisSetDriver::class)]
class PredisSetDriverIntegrationTest extends TestCase
{
    private Client $redis;

    private string $prefix;

    protected function setUp(): void
    {
        $url = getenv('HERMES_TEST_REDIS');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('Set HERMES_TEST_REDIS to run the Predis integration tests.');
        }
        $this->redis = new Client($url);
        try {
            $this->redis->ping();
        } catch (ConnectionException $e) {
            self::markTestSkipped("Redis is not reachable at {$url}: {$e->getMessage()}");
        }
        $this->prefix = 'hermes_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (isset($this->prefix)) {
            $this->redis->del([$this->prefix, $this->prefix . '_high', $this->prefix . '_schedule']);
        }
    }

    public function testSendAndWait(): void
    {
        $driver = $this->driver();
        $driver->send(new Message('order.paid', ['id' => 7]));

        $received = $this->receive($driver, 1);

        self::assertCount(1, $received);
        self::assertSame('order.paid', $received[0][0]->getType());
        self::assertSame(['id' => 7], $received[0][0]->getPayload());
        self::assertSame(100, $received[0][1]);
    }

    public function testDelayedMessageIsMovedToTheQueueWhenDue(): void
    {
        $driver = $this->driver();
        $driver->send(new Message('reminder', null, null, null, microtime(true) + 1.5));

        self::assertSame(1, $this->redis->zcard($this->prefix . '_schedule'));
        self::assertSame(0, $this->redis->scard($this->prefix));

        $started = microtime(true);
        $received = $this->receive($driver, 1);

        self::assertSame('reminder', $received[0][0]->getType());
        self::assertGreaterThanOrEqual(1.0, microtime(true) - $started);
        self::assertSame(0, $this->redis->zcard($this->prefix . '_schedule'));
    }

    public function testHigherPriorityIsTakenFirst(): void
    {
        $driver = $this->driver();
        $driver->setupPriorityQueue($this->prefix . '_high', 200);
        $driver->send(new Message('low'));
        $driver->send(new Message('high'), 200);

        $received = $this->receive($driver, 2);

        self::assertSame(['high', 'low'], array_map(static fn(array $item): string => $item[0]->getType(), $received));
        self::assertSame([200, 100], array_column($received, 1));
    }

    private function driver(): PredisSetDriver
    {
        return new PredisSetDriver($this->redis, $this->prefix, 1, $this->prefix . '_schedule');
    }

    /**
     * @return list<array{0: MessageInterface, 1: int}>
     */
    private function receive(PredisSetDriver $driver, int $count): array
    {
        $received = [];
        $driver->setMaxProcessItems($count);
        $driver->wait(static function (MessageInterface $message, int $priority) use (&$received): bool {
            $received[] = [$message, $priority];
            return true;
        });
        return $received;
    }
}
