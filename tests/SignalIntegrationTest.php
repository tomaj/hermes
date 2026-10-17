<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Aws\Sqs\SqsClient;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Logger\TestLogger;

/**
 * SIGTERM stops a real worker gracefully (#116): an idle worker stops at its next shutdown check,
 * a busy one finishes the message, acknowledges it and leaves the rest in the queue.
 * Set HERMES_TEST_REDIS, HERMES_TEST_RABBITMQ and HERMES_TEST_SQS to run it.
 *
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Shutdown\SignalStop
 */
#[RequiresPhpExtension('pcntl')]
#[RequiresPhpExtension('posix')]
class SignalIntegrationTest extends TestCase
{
    /** @var list<\Closure(): void> */
    private array $cleanup = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->cleanup as $cleanup) {
            $cleanup();
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function drivers(): array
    {
        return ['redis' => ['redis'], 'predis' => ['predis'], 'rabbitmq' => ['rabbitmq'], 'sqs' => ['sqs']];
    }

    #[DataProvider('drivers')]
    public function testIdleWorkerStopsOnSigterm(string $name): void
    {
        [$driver] = $this->driver($name);
        $logger = new TestLogger();
        $dispatcher = new Dispatcher($driver, $logger);

        // an outside process sends the signal while the worker waits for messages
        exec(sprintf('(sleep 1; kill -TERM %d) > /dev/null 2>&1 &', posix_getpid()));
        $started = microtime(true);
        $dispatcher->handle();

        self::assertLessThan(5.0, microtime(true) - $started);
        self::assertSame([['notice', 'Exiting hermes dispatcher - received SIGTERM']], array_map(
            static fn(array $log): array => [$log['level'], (string) $log['message']],
            $logger->getLogs(),
        ));
    }

    #[DataProvider('drivers')]
    public function testBusyWorkerFinishesTheMessageAndLeavesTheRest(string $name): void
    {
        [$driver, $remaining] = $this->driver($name);
        $driver->send(new Message('job', [], 'first'));
        $driver->send(new Message('job', [], 'second'));

        $seen = new \ArrayObject();
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', new readonly class ($seen) implements HandlerInterface {
            /** @param \ArrayObject<int, string> $seen */
            public function __construct(private \ArrayObject $seen) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                posix_kill(posix_getpid(), SIGTERM);
                $this->seen[] = $message->getId();

                return true;
            }
        });
        $dispatcher->handle();

        self::assertCount(1, $seen, 'one message is handled, then the worker stops');
        self::assertSame(1, $remaining(), 'the handled message is acknowledged, the other stays queued');
    }

    /**
     * @return array{DriverInterface, \Closure(): int}
     */
    private function driver(string $name): array
    {
        $id = 'hermes-it-' . bin2hex(random_bytes(4));

        return match ($name) {
            'redis' => $this->redisDriver($id),
            'predis' => $this->predisDriver($id),
            'rabbitmq' => $this->rabbitDriver($id),
            default => $this->sqsDriver($id),
        };
    }

    private function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::markTestSkipped("Set {$name} to run this integration test.");
        }

        return $value;
    }

    /**
     * @return array{DriverInterface, \Closure(): int}
     */
    private function redisDriver(string $key): array
    {
        $parts = parse_url($this->env('HERMES_TEST_REDIS'));
        $redis = new Redis();
        $redis->connect($parts['host'] ?? '127.0.0.1', $parts['port'] ?? 6379);
        $this->cleanup[] = static function () use ($redis, $key): void {
            $redis->del($key, $key . '_schedule');
        };

        return [new RedisSetDriver($redis, $key, 0, $key . '_schedule'), static fn(): int => (int) $redis->scard($key)];
    }

    /**
     * @return array{DriverInterface, \Closure(): int}
     */
    private function predisDriver(string $key): array
    {
        $redis = new Client($this->env('HERMES_TEST_REDIS'));
        $this->cleanup[] = static function () use ($redis, $key): void {
            $redis->del([$key, $key . '_schedule']);
        };

        return [new PredisSetDriver($redis, $key, 0, $key . '_schedule'), static fn(): int => $redis->scard($key)];
    }

    /**
     * @return array{DriverInterface, \Closure(): int}
     */
    private function rabbitDriver(string $queue): array
    {
        $config = new AMQPConnectionConfig();
        $config->setHost($this->env('HERMES_TEST_RABBITMQ'));
        $config->setIsLazy(true);
        $connection = AMQPConnectionFactory::create($config);
        $count = static function () use ($config, $queue, $connection): int {
            // the worker process would exit here; its connection closes and unacked prefetch goes back to the queue
            $connection->close();
            $declared = AMQPConnectionFactory::create($config)->channel()->queue_declare($queue, true);

            return is_array($declared) && is_int($declared[1]) ? $declared[1] : -1;
        };
        $this->cleanup[] = static function () use ($config, $queue): void {
            AMQPConnectionFactory::create($config)->channel()->queue_delete($queue);
        };

        return [new LazyRabbitMqDriver($connection, $queue), $count];
    }

    /**
     * @return array{DriverInterface, \Closure(): int}
     */
    private function sqsDriver(string $queue): array
    {
        $client = new SqsClient([
            'region' => 'us-east-1',
            'version' => '2012-11-05',
            'endpoint' => $this->env('HERMES_TEST_SQS'),
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
        $driver = new AmazonSqsDriver($client, $queue, ['VisibilityTimeout' => 30]);
        $url = $client->getQueueUrl(['QueueName' => $queue])->get('QueueUrl');
        self::assertIsString($url);
        $this->cleanup[] = static function () use ($client, $url): void {
            $client->deleteQueue(['QueueUrl' => $url]);
        };
        $count = static function () use ($client, $url): int {
            $attributes = $client->getQueueAttributes([
                'QueueUrl' => $url,
                'AttributeNames' => ['ApproximateNumberOfMessages', 'ApproximateNumberOfMessagesNotVisible'],
            ])->get('Attributes');
            self::assertIsArray($attributes);
            $visible = $attributes['ApproximateNumberOfMessages'] ?? null;
            $inFlight = $attributes['ApproximateNumberOfMessagesNotVisible'] ?? null;
            self::assertIsNumeric($visible);
            self::assertIsNumeric($inFlight);

            return (int) $visible + (int) $inFlight;
        };

        return [$driver, $count];
    }
}
