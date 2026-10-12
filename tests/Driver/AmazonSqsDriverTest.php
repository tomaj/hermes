<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\NotSupportedException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\SerializerInterface;
use Tomaj\Hermes\Test\Double\SqsClientDouble;

/**
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class AmazonSqsDriverTest extends TestCase
{
    public function testSetupPriorityQueueThrowsException(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue'])
                       ->getMock();
        $client->expects(self::once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        $driver = new AmazonSqsDriver($client, 'test-queue');

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('AmazonSQS is not supporting priority queues now');

        $driver->setupPriorityQueue('test', 100);
    }

    public function testConstructorCreatesQueue(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue'])
                       ->getMock();
        $client->expects(self::once())
               ->method('createQueue')
               ->with([
                   'QueueName' => 'test-queue',
                   'Attributes' => [],
               ])
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        $driver = new AmazonSqsDriver($client, 'test-queue');

        self::assertInstanceOf(AmazonSqsDriver::class, $driver);
    }

    public function testConstructorWithQueueAttributes(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue'])
                       ->getMock();
        $attributes = ['VisibilityTimeout' => '60'];

        $client->expects(self::once())
               ->method('createQueue')
               ->with([
                   'QueueName' => 'test-queue',
                   'Attributes' => $attributes,
               ])
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        $driver = new AmazonSqsDriver($client, 'test-queue', $attributes);

        self::assertInstanceOf(AmazonSqsDriver::class, $driver);
    }

    public function testTraitsAreUsed(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue'])
                       ->getMock();
        $client->expects(self::once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        $driver = new AmazonSqsDriver($client, 'test-queue');

        // Test MaxItemsTrait methods
        self::assertEquals(0, $driver->processed());
        self::assertTrue($driver->shouldProcessNext());

        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = $this->createMock(SerializerInterface::class);
        $driver->setSerializer($customSerializer);

    }

    public function testSendMessage(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
                       ->disableOriginalConstructor()
                       ->onlyMethods(['createQueue', 'sendMessage'])
                       ->getMock();
        $client->expects(self::once())
               ->method('createQueue')
               ->willReturn($this->createMockResult(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        $client->expects(self::once())
               ->method('sendMessage')
               ->with(self::callback(function (mixed $params): bool {
                   return is_array($params)
                          && ($params['QueueUrl'] ?? null) === 'https://sqs.region.amazonaws.com/123456789/test-queue'
                          && isset($params['MessageBody']);
               }));

        $driver = new AmazonSqsDriver($client, 'test-queue');

        $message = new Message('test', ['data' => 'value']);
        $result = $driver->send($message);

        self::assertTrue($result);
    }

    /**
     * @param array<string, string> $data
     */
    private function createMockResult(array $data): object
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
