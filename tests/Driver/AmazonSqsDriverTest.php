<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\NotSupportedException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\SerializerInterface;

/**
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class AmazonSqsDriverTest extends TestCase
{
    public function testSetupPriorityQueueThrowsException(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue'])
                       ->getMock();
        $client->expects($this->once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
               
        $driver = new AmazonSqsDriver($client, 'test-queue');
        
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage("AmazonSQS is not supporting priority queues now");
        
        $driver->setupPriorityQueue('test', 100);
    }
    
    public function testConstructorCreatesQueue(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue'])
                       ->getMock();
        $client->expects($this->once())
               ->method('createQueue')
               ->with([
                   'QueueName' => 'test-queue',
                   'Attributes' => [],
               ])
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
               
        $driver = new AmazonSqsDriver($client, 'test-queue');
        
        $this->assertInstanceOf(AmazonSqsDriver::class, $driver);
    }
    
    public function testConstructorWithQueueAttributes(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue'])
                       ->getMock();
        $attributes = ['VisibilityTimeout' => '60'];
        
        $client->expects($this->once())
               ->method('createQueue')
               ->with([
                   'QueueName' => 'test-queue',
                   'Attributes' => $attributes,
               ])
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
               
        $driver = new AmazonSqsDriver($client, 'test-queue', $attributes);
        
        $this->assertInstanceOf(AmazonSqsDriver::class, $driver);
    }
    
    public function testTraitsAreUsed(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue'])
                       ->getMock();
        $client->expects($this->once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
               
        $driver = new AmazonSqsDriver($client, 'test-queue');
        
        // Test MaxItemsTrait methods
        $this->assertEquals(0, $driver->processed());
        $this->assertTrue($driver->shouldProcessNext());
        
        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = $this->createMock(SerializerInterface::class);
        $driver->setSerializer($customSerializer);
        
        // No exception should be thrown
        $this->assertTrue(true);
    }
    
    public function testSendMessage(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
                       ->disableOriginalConstructor()
                       ->addMethods(['createQueue', 'sendMessage'])
                       ->getMock();
        $client->expects($this->once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
        
        $client->expects($this->once())
               ->method('sendMessage')
               ->with($this->callback(function ($params) {
                   return $params['QueueUrl'] === 'https://sqs.region.amazonaws.com/123456789/test-queue' &&
                          isset($params['MessageBody']);
               }));
               
        $driver = new AmazonSqsDriver($client, 'test-queue');
        
        $message = new Message('test', ['data' => 'value']);
        $result = $driver->send($message);
        
        $this->assertTrue($result);
    }
    
    private function createMockResult(array $data): object
    {
        $result = new class($data) {
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
        
        return $result;
    }
}
