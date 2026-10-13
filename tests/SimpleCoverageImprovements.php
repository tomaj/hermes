<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use DateTime;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Test\Double\PredisClientDouble;
use Tomaj\Hermes\Test\Double\SqsClientDouble;
use Tomaj\Hermes\Test\Driver\DummyDriver;

/**
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 */
class SimpleCoverageImprovements extends TestCase
{
    /**
     * @requires extension redis
     */
    public function testRedisSetDriverScheduledMessageHandling(): void
    {
        $redis = $this->getMockBuilder(Redis::class)
                      ->disableOriginalConstructor()
                      ->onlyMethods(['zAdd', 'sAdd', 'zRangeByScore', 'zRem'])
                      ->getMock();

        $driver = new RedisSetDriver($redis, 'test_queue');
        $serializer = new MessageSerializer();
        $driver->setSerializer($serializer);

        // Test scheduled message (future)
        $futureTime = microtime(true) + 3600;
        $scheduledMessage = new Message('scheduled.task', ['data' => 'future'], 'sched1', microtime(true), $futureTime);

        $redis->expects(self::once())
              ->method('zAdd')
              ->with('hermes_schedule', $futureTime, self::anything())
              ->willReturn(1);

        self::assertTrue($driver->send($scheduledMessage));

        // Test scheduled message processing
        $processedMessages = [];
        $redis->method('zRangeByScore')
              ->willReturn(['serialized_message_data']);

        $redis->method('zRem')
              ->willReturn(1);

        $callback = function (MessageInterface $message) use (&$processedMessages) {
            $processedMessages[] = $message;
            return true;
        };

        // This should call wait() which processes scheduled messages
        $driver->wait($callback);
    }

    public function testPredisSetDriverWithCallbacks(): void
    {
        $predis = $this->getMockBuilder(PredisClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['zadd', 'sadd', 'spop', 'zrangebyscore', 'zrem'])
                       ->getMock();

        $driver = new PredisSetDriver($predis, 'predis_queue');

        // Test with default serializer
        $message = new Message('test.event', ['data' => 'test']);

        $predis->expects(self::once())
               ->method('sadd')
               ->willReturn(1);

        self::assertTrue($driver->send($message));

        // Test wait with empty queue
        $predis->method('spop')->willReturn(null);
        $predis->method('zrangebyscore')->willReturn([]);

        $processedCount = 0;
        $callback = function (MessageInterface $message) use (&$processedCount) {
            $processedCount++;
            return true;
        };

        $driver->wait($callback);
        self::assertEquals(0, $processedCount);
    }

    public function testAmazonSqsDriverWithAttributes(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue', 'sendMessage'])
                       ->getMock();

        // Test with queue attributes
        $queueAttributes = ['VisibilityTimeout' => 30];
        $driver = new AmazonSqsDriver($client, 'test-queue', $queueAttributes);

        $client->expects(self::once())
               ->method('createQueue')
               ->with(self::callback(function (mixed $params): bool {
                   if (!is_array($params)) {
                       return false;
                   }
                   $attributes = $params['Attributes'] ?? null;

                   return is_array($attributes) && ($attributes['VisibilityTimeout'] ?? null) === '30';
               }))
               ->willReturn($this->createMockAwsResult(['QueueUrl' => 'https://sqs.amazonaws.com/test']));

        $message = new Message('sqs.test', ['data' => 'test']);

        $client->expects(self::once())
               ->method('sendMessage')
               ->willReturn($this->createMockAwsResult(['MessageId' => 'msg123']));

        self::assertTrue($driver->send($message));
    }

    public function testLazyRabbitMqDriverChannelOperations(): void
    {
        $channel = $this->getMockBuilder(AMQPChannel::class)
                        ->disableOriginalConstructor()
                        ->onlyMethods(['queue_declare', 'basic_publish'])
                        ->getMock();

        $connection = $this->getMockBuilder(AMQPLazyConnection::class)
                           ->disableOriginalConstructor()
                           ->onlyMethods(['channel'])
                           ->getMock();

        $connection->method('channel')->willReturn($channel);

        $driver = new LazyRabbitMqDriver($connection, 'rabbit_queue');

        $channel->expects(self::once())
               ->method('queue_declare')
               ->with('rabbit_queue', false, false, false, false);

        $channel->expects(self::once())
               ->method('basic_publish');

        $message = new Message('rabbit.event', ['action' => 'test']);
        self::assertTrue($driver->send($message));
    }

    public function testDispatcherUnregisterMethods(): void
    {
        $driver = new DummyDriver([]);
        $dispatcher = new Dispatcher($driver);

        $handler1 = new class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };

        $handler2 = new class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                return true;
            }
        };

        // Register handlers
        $dispatcher->registerHandler('test.event', $handler1);
        $dispatcher->registerHandler('test.event', $handler2);
        $dispatcher->registerHandler('other.event', $handler1);

        // Test unregisterHandler
        $dispatcher->unregisterHandler('test.event', $handler1);

        // Test unregisterAllHandlers
        $dispatcher->unregisterAllHandlers();
        self::expectNotToPerformAssertions();
    }

    public function testDispatcherWithLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $driver = new DummyDriver([]);
        $dispatcher = new Dispatcher($driver, $logger);

        // Test that logging happens
        $logger->expects(self::atLeastOnce())
               ->method('log');

        $message = new Message('log.test', ['data' => 'test']);
        $driver->send($message);

        $handler = new class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new \Exception('Test exception for logging');
            }
        };

        $dispatcher->registerHandler('log.test', $handler);
        $dispatcher->handle();
    }

    public function testPredisShutdownBasicMethods(): void
    {
        $predis = $this->getMockBuilder(PredisClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['get'])
                       ->getMock();

        // Test with null return (no shutdown set)
        $predis->method('get')->willReturn(null);

        $shutdown = new PredisShutdown($predis, 'test_shutdown');
        $startTime = new DateTime();

        self::assertFalse($shutdown->shouldShutdown($startTime));
    }

    /**
     * @requires extension redis
     */
    public function testRedisShutdownBasicMethods(): void
    {
        $redis = $this->getMockBuilder(Redis::class)
                      ->disableOriginalConstructor()
                      ->onlyMethods(['get'])
                      ->getMock();

        // Test with false return (no shutdown set)
        $redis->method('get')->willReturn(false);

        $shutdown = new RedisShutdown($redis, 'test_shutdown');
        $startTime = new DateTime();

        self::assertFalse($shutdown->shouldShutdown($startTime));
    }

    public function testDispatcherNextRetryMethod(): void
    {
        $driver = new DummyDriver([]);
        $dispatcher = new Dispatcher($driver);

        // Create message with retries
        $message = new Message('retry.test', ['data' => 'test'], 'id1', microtime(true), null, 5);

        // Use reflection to test private nextRetry method
        $reflection = new \ReflectionClass($dispatcher);
        $method = $reflection->getMethod('nextRetry');
        $method->setAccessible(true);

        $nextRetryTime = $method->invoke($dispatcher, $message);
        self::assertIsFloat($nextRetryTime);
        self::assertGreaterThan(microtime(true), $nextRetryTime);
    }

    public function testMessageSerializerEdgeCases(): void
    {
        $serializer = new MessageSerializer();

        // Test message with very long ID
        $longId = str_repeat('x', 1000);
        $message = new Message('test.event', ['data' => 'test'], $longId);

        $serialized = $serializer->serialize($message);
        $unserialized = $serializer->unserialize($serialized);

        self::assertEquals($longId, $unserialized->getId());

        // Test message with empty payload
        $emptyMessage = new Message('empty.event', []);
        $serialized = $serializer->serialize($emptyMessage);
        $unserialized = $serializer->unserialize($serialized);

        self::assertEquals([], $unserialized->getPayload());
    }

    /**
     * @param array<string, string> $data
     */
    private function createMockAwsResult(array $data): object
    {
        return new class ($data) {
            /** @param array<string, string> $data */
            public function __construct(private array $data) {}

            public function get(string $key): ?string
            {
                return $this->data[$key] ?? null;
            }
        };
    }
}
