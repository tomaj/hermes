<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\NotSupportedException;
use Tomaj\Hermes\SerializerInterface;

/**
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class LazyRabbitMqDriverTest extends TestCase
{
    public function testSetupPriorityQueueThrowsException(): void
    {
        // We can test this method without a real connection since it just throws an exception
        $connection = self::createStub(AbstractConnection::class);
        $driver = new LazyRabbitMqDriver($connection, 'test_queue');

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessageIs('LazyRabbitMqDriver is not supporting priority queues now');

        $driver->setupPriorityQueue('test', 100);
    }

    public function testConstructorSetsProperties(): void
    {
        $connection = self::createStub(AbstractConnection::class);
        $amqpProperties = ['delivery_mode' => 2];
        $driver = new LazyRabbitMqDriver($connection, 'test_queue', $amqpProperties, 300, 'custom_tag');

        // We can't access private properties directly, but we can test that the constructor doesn't throw
        self::assertSame(0, $driver->processed());
    }

    public function testTraitsAreUsed(): void
    {
        $connection = self::createStub(AbstractConnection::class);
        $driver = new LazyRabbitMqDriver($connection, 'test_queue');

        // Test MaxItemsTrait methods
        self::assertSame(0, $driver->processed());
        self::assertTrue($driver->shouldProcessNext());

        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = self::createStub(SerializerInterface::class);
        $driver->setSerializer($customSerializer);
    }

    public function testGetChannelCreatesChannel(): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $channel = $this->createMock(AMQPChannel::class);

        $connection->expects(self::once())
                   ->method('channel')
                   ->willReturn($channel);

        $channel->expects(self::once())
                ->method('queue_declare')
                ->with('test_queue', false, true, false, false);

        $driver = new LazyRabbitMqDriver($connection, 'test_queue');

        // Use reflection to call the private getChannel method
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getChannel');

        $result = $method->invoke($driver);
        self::assertSame($channel, $result);

        // Call it again to test that it returns the same channel (cached)
        $result2 = $method->invoke($driver);
        self::assertSame($channel, $result2);
    }
}
