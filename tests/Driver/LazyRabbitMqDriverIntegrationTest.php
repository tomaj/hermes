<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use DateTimeInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Test\IntegrationServices;

/**
 * Runs against a real RabbitMQ. Set HERMES_TEST_RABBITMQ to the
 * host (guest/guest, port 5672, for example 127.0.0.1) to run it; without it the tests are skipped.
 */
#[CoversClass(LazyRabbitMqDriver::class)]
class LazyRabbitMqDriverIntegrationTest extends TestCase
{
    private string $host;

    private string $queue;

    #[\Override]
    protected function setUp(): void
    {
        $host = IntegrationServices::get('HERMES_TEST_RABBITMQ');
        $this->host = $host;
        $this->queue = 'hermes-it-' . bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (isset($this->host)) {
            $connection = $this->connection();
            $connection->channel()->queue_delete($this->queue);
            $connection->close();
        }
    }

    private function connection(): AbstractConnection
    {
        $config = new AMQPConnectionConfig();
        $config->setHost($this->host);
        $config->setIsLazy(true);
        return AMQPConnectionFactory::create($config);
    }

    public function testFailedMessageIsRequeuedAndHandledMessageIsAcknowledged(): void
    {
        new LazyRabbitMqDriver($this->connection(), $this->queue)->send(new Message('job', [], 'rmq-1'));

        $failingWorker = $this->connection();
        try {
            new LazyRabbitMqDriver($failingWorker, $this->queue)->wait(function (): void {
                throw new RuntimeException('handler failed');
            });
        } catch (\Throwable $e) {
            self::assertInstanceOf(RuntimeException::class, $e);
        }
        // the failing worker process exits
        $failingWorker->close();

        $ids = [];
        $driver = new LazyRabbitMqDriver($this->connection(), $this->queue);
        $driver->setMaxProcessItems(1);
        $driver->wait(function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });
        self::assertSame(['rmq-1'], $ids);

        $channel = $this->connection()->channel();
        $declared = $channel->queue_declare($this->queue, true);
        self::assertIsArray($declared);
        self::assertSame(0, $declared[1], 'acknowledged message must be gone');
    }

    public function testQueueIsDurable(): void
    {
        new LazyRabbitMqDriver($this->connection(), $this->queue)->send(new Message('job', []));

        $channel = $this->connection()->channel();
        // passive declare with durable=true only succeeds when the queue is durable
        $declared = $channel->queue_declare($this->queue, false, true, false, false);
        self::assertIsArray($declared);
        self::assertSame(1, $declared[1]);
    }

    public function testIdleWorkerBlocksInsteadOfSpinningAndStillStops(): void
    {
        $driver = new LazyRabbitMqDriver($this->connection(), $this->queue);
        $deadline = microtime(true) + 2.0;
        $driver->setShutdown(new readonly class ($deadline) implements ShutdownInterface {
            public function __construct(private float $deadline) {}

            #[\Override]
            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                return microtime(true) >= $this->deadline;
            }

            #[\Override]
            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return false;
            }
        });

        $start = microtime(true);
        $cpuStart = $this->cpuSeconds();
        try {
            $driver->wait(static function (): void {});
            self::fail('Exception expected');
        } catch (ShutdownException) {
        }
        $elapsed = microtime(true) - $start;
        $cpu = $this->cpuSeconds() - $cpuStart;

        // The default one-second idle timeout stops the worker within a second of the shutdown signal.
        self::assertGreaterThanOrEqual(1.9, $elapsed);
        self::assertLessThan(3.5, $elapsed);
        // A busy loop burns about one CPU second per wall second.
        self::assertLessThan(0.3, $cpu, 'an idle worker must block, not spin');
    }

    public function testBlockingWaitPicksUpAMessageAtOnce(): void
    {
        $driver = new LazyRabbitMqDriver($this->connection(), $this->queue, [], 30);
        $driver->setMaxProcessItems(3);
        $producer = new LazyRabbitMqDriver($this->connection(), $this->queue);
        for ($i = 1; $i <= 3; $i++) {
            $producer->send(new Message('job', [], 'rmq-fast-' . $i));
        }

        $ids = [];
        $start = microtime(true);
        $driver->wait(static function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });

        self::assertSame(['rmq-fast-1', 'rmq-fast-2', 'rmq-fast-3'], $ids);
        // A 30 second refresh interval only bounds the idle wait: queued messages are not delayed.
        self::assertLessThan(5.0, microtime(true) - $start);
    }

    private function cpuSeconds(): float
    {
        $usage = getrusage();
        self::assertIsArray($usage);
        $seconds = 0.0;
        foreach (['ru_utime', 'ru_stime'] as $kind) {
            $sec = $usage[$kind . '.tv_sec'] ?? null;
            $usec = $usage[$kind . '.tv_usec'] ?? null;
            self::assertIsInt($sec);
            self::assertIsInt($usec);
            $seconds += $sec + $usec / 1e6;
        }
        return $seconds;
    }
}
