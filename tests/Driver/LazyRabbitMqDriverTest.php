<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
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
        $connection = $this->createMock(AMQPLazyConnection::class);
        $driver = new LazyRabbitMqDriver($connection, 'test_queue');
        
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage("LazyRabbitMqDriver is not supporting priority queues now");
        
        $driver->setupPriorityQueue('test', 100);
    }
    
    public function testConstructorSetsProperties(): void
    {
        $connection = $this->createMock(AMQPLazyConnection::class);
        $amqpProperties = ['delivery_mode' => 2];
        $driver = new LazyRabbitMqDriver($connection, 'test_queue', $amqpProperties, 300, 'custom_tag');
        
        // We can't access private properties directly, but we can test that the constructor doesn't throw
        $this->assertInstanceOf(LazyRabbitMqDriver::class, $driver);
    }
    
    public function testTraitsAreUsed(): void
    {
        $connection = $this->createMock(AMQPLazyConnection::class);
        $driver = new LazyRabbitMqDriver($connection, 'test_queue');
        
        // Test MaxItemsTrait methods
        $this->assertEquals(0, $driver->processed());
        $this->assertTrue($driver->shouldProcessNext());
        
        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = $this->createMock(SerializerInterface::class);
        $driver->setSerializer($customSerializer);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }
    
    public function testGetChannelCreatesChannel(): void
    {
        $connection = $this->createMock(AMQPLazyConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        
        $connection->expects($this->once())
                   ->method('channel')
                   ->willReturn($channel);
                   
        $channel->expects($this->once())
                ->method('queue_declare')
                ->with('test_queue', false, false, false, false);
        
        $driver = new LazyRabbitMqDriver($connection, 'test_queue');
        
        // Use reflection to call the private getChannel method
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('getChannel');
        $method->setAccessible(true);
        
        $result = $method->invoke($driver);
        $this->assertSame($channel, $result);
        
        // Call it again to test that it returns the same channel (cached)
        $result2 = $method->invoke($driver);
        $this->assertSame($channel, $result2);
    }
}
