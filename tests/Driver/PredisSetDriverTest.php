<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Predis\Client as PredisClient;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\SerializerInterface;

/**
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class PredisSetDriverTest extends TestCase
{
    public function testSetupPriorityQueue(): void
    {
        $redis = $this->createMock(PredisClient::class);
        $driver = new PredisSetDriver($redis, 'test_key');
        
        // Test that we can setup a priority queue
        $driver->setupPriorityQueue('high_priority', 200);
        
        // This method doesn't return anything, so we just test it doesn't throw
        $this->assertTrue(true);
    }
    
    public function testConstructorSetsDefaults(): void
    {
        $redis = $this->createMock(PredisClient::class);
        $driver = new PredisSetDriver($redis);
        
        // Constructor should work with defaults
        $this->assertInstanceOf(PredisSetDriver::class, $driver);
    }
    
    public function testConstructorWithParameters(): void
    {
        $redis = $this->createMock(PredisClient::class);
        $driver = new PredisSetDriver($redis, 'custom_key', 5, 'custom_schedule');
        
        // Constructor should work with custom parameters
        $this->assertInstanceOf(PredisSetDriver::class, $driver);
    }
    
    public function testTraitsAreUsed(): void
    {
        $redis = $this->createMock(PredisClient::class);
        $driver = new PredisSetDriver($redis, 'test_key');
        
        // Test MaxItemsTrait methods
        $this->assertEquals(0, $driver->processed());
        $this->assertTrue($driver->shouldProcessNext());
        
        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = $this->createMock(SerializerInterface::class);
        $driver->setSerializer($customSerializer);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }
    
    public function testSendWithUnknownPriority(): void
    {
        $redis = $this->createMock(PredisClient::class);
        $driver = new PredisSetDriver($redis, 'test_key');
        
        $message = new Message('test', ['data' => 'value']);
        
        $this->expectException(UnknownPriorityException::class);
        $this->expectExceptionMessage("Unknown priority 999");
        
        $driver->send($message, 999);
    }
    
    public function testSendWithValidPriority(): void
    {
        $redis = $this->getMockBuilder(PredisClient::class)
                      ->disableOriginalConstructor()
                      ->addMethods(['sadd'])
                      ->getMock();
        $redis->expects($this->once())
              ->method('sadd')
              ->willReturn(1);
              
        $driver = new PredisSetDriver($redis, 'test_key');
        
        $message = new Message('test', ['data' => 'value']);
        $result = $driver->send($message, 100); // 100 is the default priority that gets set up
        
        $this->assertTrue($result);
    }
}
