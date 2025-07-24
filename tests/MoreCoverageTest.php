<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use DateTime;

/**
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class MoreCoverageTest extends TestCase
{
    public function testRedisSetDriverScheduledMessages(): void
    {
        $redis = $this->getMockBuilder(\Redis::class)
                      ->disableOriginalConstructor()
                      ->onlyMethods(['zAdd', 'sAdd'])
                      ->getMock();
        
        $driver = new RedisSetDriver($redis, 'test_queue');
        
        // Test scheduled message (do budúcnosti)
        $futureTime = microtime(true) + 3600;
        $scheduledMessage = new Message('scheduled.task', ['action' => 'cleanup'], 'id1', microtime(true), $futureTime);
        
        $redis->expects($this->once())
              ->method('zAdd')
              ->with('hermes_schedule', $futureTime, $this->anything())
              ->willReturn(1);
        
        $result = $driver->send($scheduledMessage);
        $this->assertTrue($result);
        
        // Test immediate message
        $immediateMessage = new Message('immediate.task', ['action' => 'notify'], 'id2');
        
        $redis->expects($this->once())
              ->method('sAdd')
              ->with('test_queue', $this->anything())
              ->willReturn(1);
        
        $result = $driver->send($immediateMessage);
        $this->assertTrue($result);
    }
    
    public function testPredisSetDriverScheduledMessages(): void
    {
        $predis = $this->getMockBuilder(\Predis\Client::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['zadd', 'sadd'])
                       ->getMock();
        
        $driver = new PredisSetDriver($predis, 'test_queue');
        
        // Test scheduled message (do budúcnosti)
        $futureTime = microtime(true) + 3600;
        $scheduledMessage = new Message('scheduled.task', ['action' => 'cleanup'], 'id1', microtime(true), $futureTime);
        
        $predis->expects($this->once())
               ->method('zadd')
               ->willReturn(1);
        
        $result = $driver->send($scheduledMessage);
        $this->assertTrue($result);
        
        // Test immediate message
        $immediateMessage = new Message('immediate.task', ['action' => 'notify'], 'id2');
        
        $predis->expects($this->once())
               ->method('sadd')
               ->willReturn(1);
        
        $result = $driver->send($immediateMessage);
        $this->assertTrue($result);
    }
    
    public function testAmazonSqsDriverSendMessage(): void
    {
        $client = $this->getMockBuilder(\Aws\Sqs\SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue', 'sendMessage'])
                       ->getMock();
        
        $client->expects($this->once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.amazonaws.com/test']));
        
        $driver = new AmazonSqsDriver($client, 'test-queue');
        
        $message = new Message('notification', ['user_id' => 123, 'message' => 'Hello']);
        
        $client->expects($this->once())
               ->method('sendMessage')
               ->with($this->callback(function($params) {
                   return isset($params['QueueUrl']) && isset($params['MessageBody']);
               }));
        
        $result = $driver->send($message);
        $this->assertTrue($result);
    }
    
    public function testPredisShutdownConstructor(): void
    {
        // Test constructor s default key
        $predis = $this->createMock(\Predis\Client::class);
        $shutdown = new PredisShutdown($predis);
        $this->assertInstanceOf(PredisShutdown::class, $shutdown);
        
        // Test constructor s custom key
        $shutdown2 = new PredisShutdown($predis, 'custom_shutdown_key');
        $this->assertInstanceOf(PredisShutdown::class, $shutdown2);
    }
    
    public function testRedisShutdownConstructor(): void
    {
        // Test constructor s default key
        $redis = $this->createMock(\Redis::class);
        $shutdown = new RedisShutdown($redis);
        $this->assertInstanceOf(RedisShutdown::class, $shutdown);
        
        // Test constructor s custom key  
        $shutdown2 = new RedisShutdown($redis, 'custom_shutdown_key');
        $this->assertInstanceOf(RedisShutdown::class, $shutdown2);
    }
    
    public function testDispatcherWaitFeature(): void
    {
        // Test wait functionality ktorá nemusí byť kompletne pokrytá
        $mockDriver = $this->getMockBuilder(\Tomaj\Hermes\Driver\DriverInterface::class)
                           ->getMock();
        
        $mockDriver->expects($this->once())
                   ->method('wait')
                   ->with($this->isType('callable'), [100]);
        
        $dispatcher = new Dispatcher($mockDriver);
        $dispatcher->handle([100]);
    }
    
    public function testMessageWithAllParameters(): void
    {
        // Test Message constructor s všetkými parametrami
        $currentTime = microtime(true);
        $executeAt = $currentTime + 3600;
        
        $message = new Message(
            'test.event',
            ['key' => 'value', 'number' => 42],
            'custom-id-123',
            $currentTime,
            $executeAt,
            5
        );
        
        $this->assertEquals('test.event', $message->getType());
        $this->assertEquals(['key' => 'value', 'number' => 42], $message->getPayload());
        $this->assertEquals('custom-id-123', $message->getId());
        $this->assertEquals($currentTime, $message->getCreated());
        $this->assertEquals($executeAt, $message->getExecuteAt());
        $this->assertEquals(5, $message->getRetries());
    }
    
    public function testMessageWithNullExecuteAt(): void
    {
        // Test Message s null executeAt
        $message = new Message('test.event', ['data' => 'test'], 'id', microtime(true), null, 0);
        $this->assertNull($message->getExecuteAt());
    }
    
    private function createMockResult(array $data): object
    {
        return new class($data) {
            private $data;
            
            public function __construct($data)
            {
                $this->data = $data;
            }
            
            public function get($key)
            {
                return $this->data[$key] ?? null;
            }
        };
    }
}