<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * Class AmazonSqsDriverTest
 * @package Tomaj\Hermes\Test\Driver
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\HermesException
 */
class AmazonSqsDriverTest extends TestCase
{
    /**
     * @param string[] $methods
     * @return MockObject
     */
    private function prepareClient(array $methods = []): MockObject
    {
        if (!in_array('createQueue', $methods)) {
            $methods[] = 'createQueue';
        }
        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods($methods)
            ->getMock();
        $client->expects($this->once())
            ->method('createQueue')
            ->with(['QueueName' => 'mykey1', 'Attributes' => []])->will($this->returnValue(new class {
                public function get(string $string): string
                {
                    return 'mykey1';
                }
            }));
        return $client;
    }

    public function testPredisSendMessage(): void
    {
        $message = new Message('message1key', ['a' => 'b']);
        $client = $this->prepareClient(['sendMessage']);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with([
                'QueueUrl' => 'mykey1',
                'MessageBody' => (new MessageSerializer)->serialize($message),
            ]);

        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->send($message);
    }

    public function testWaitForMessage(): void
    {
        $message = new Message('message1', ['test' => 'value']);

        $client = $this->prepareClient(['receiveMessage', 'deleteMessage']);
        $client->expects($this->once())
            ->method('receiveMessage')
            ->will($this->returnValue(['Messages' => [['ReceiptHandle' => '123x', 'Body' => (new MessageSerializer)->serialize($message)]]]));
        $client->expects($this->once())
            ->method('deleteMessage')
            ->with(['QueueUrl' => 'mykey1', 'ReceiptHandle' => '123x']);

        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->setMaxProcessItems(1);
        $processed = [];
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertCount(1, $processed);
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }

    public function testShutdownBeforeStart(): void
    {
        $client = $this->prepareClient();
        $processed = [];
        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->setShutdown(new CustomShutdown((new \DateTime())->modify("+5 minutes")));

        $this->expectException(ShutdownException::class);

        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });
    }
}
