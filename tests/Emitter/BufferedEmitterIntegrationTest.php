<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Emitter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\IntegrationServices;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * BufferedEmitter in front of a real Emitter and RedisSetDriver. Set HERMES_TEST_REDIS
 * (for example tcp://127.0.0.1:6379) to run it; without it the test is skipped.
 */
#[CoversClass(BufferedEmitter::class)]
#[CoversClass(Emitter::class)]
#[CoversClass(RedisSetDriver::class)]
#[RequiresPhpExtension('redis')]
final class BufferedEmitterIntegrationTest extends TestCase
{
    private Redis $redis;

    private string $prefix;

    #[\Override]
    protected function setUp(): void
    {
        $url = IntegrationServices::get('HERMES_TEST_REDIS');
        $parts = parse_url($url);
        $this->redis = new Redis();
        $this->redis->connect($parts['host'] ?? '127.0.0.1', $parts['port'] ?? 6379);
        $this->prefix = 'hermes_test_' . bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->prefix)) {
            $this->redis->del($this->prefix, $this->prefix . '_schedule', $this->prefix . '_200');
        }
    }

    public function testNothingReachesRedisBeforeFlushAndDiscardedMessagesNever(): void
    {
        $driver = new RedisSetDriver($this->redis, $this->prefix, 0, $this->prefix . '_schedule');
        $driver->setupPriorityQueue($this->prefix . '_200', 200);
        $emitter = new BufferedEmitter(new Emitter($driver));

        $emitter->emit(new Message('rolled-back', [], 'rolled-back'));
        self::assertSame(0, $this->redis->scard($this->prefix));
        self::assertSame(1, $emitter->discard());

        $emitter->emit(new Message('first', [], 'first'));
        $emitter->emit(new Message('urgent', [], 'urgent'), 200);
        self::assertSame(0, $this->redis->scard($this->prefix));
        self::assertSame(0, $this->redis->scard($this->prefix . '_200'));

        $emitter->flush();
        self::assertSame(1, $this->redis->scard($this->prefix));
        self::assertSame(1, $this->redis->scard($this->prefix . '_200'));

        $ids = [];
        $driver->setMaxProcessItems(2);
        $driver->setShutdown(new PollBudgetShutdown());
        $driver->wait(static function (MessageInterface $message, int $priority) use (&$ids): void {
            $ids[] = [$message->getId(), $priority];
        }, [Dispatcher::DEFAULT_PRIORITY, 200]);

        self::assertSame([['urgent', 200], ['first', Dispatcher::DEFAULT_PRIORITY]], $ids);
    }
}
