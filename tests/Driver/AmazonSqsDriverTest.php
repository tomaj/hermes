<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Restart\RestartException;

class AmazonSqsDriverTest extends TestCase
{
    private function prepareClient($queue, array $methods = [])
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
                public function get($string) {
                    return 'mykey1';
                }
            }));
        return $client;
    }

    public function testPredisSendMessage()
    {
        $message = new Message('message1key', ['a' => 'b']);
        $client = $this->prepareClient('xx', ['sendMessage']);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with([
                'QueueUrl' => 'mykey1',
                'MessageBody' => (new MessageSerializer)->serialize($message),
            ]);

        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->send($message);
    }

    public function testWaitForMessage()
    {
        $message = new Message('message1', ['test' => 'value']);

        $client = $this->prepareClient('mykey1', ['receiveMessage', 'deleteMessage']);
        $client->expects($this->once())
            ->method('receiveMessage')
            ->will($this->returnValue(['Messages' => [['ReceiptHandle' => '123x', 'Body' => (new MessageSerializer)->serialize($message)]]]));
        $client->expects($this->once())
            ->method('deleteMessage')
            ->with(['QueueUrl' => 'mykey1', 'ReceiptHandle' => '123x']);

        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->setMaxProcessItems(1);
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertCount(1, $processed);
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }

    public function testRestartBeforeStart()
    {
        $client = $this->prepareClient('mykey1', []);
        $processed = [];
        $driver = new AmazonSqsDriver($client, 'mykey1');
        $driver->setRestart(new CustomRestart((new \DateTime())->modify("+5 minutes")));

        $this->expectException(RestartException::class);

        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });
    }
}

