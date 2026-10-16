<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Result;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Test\Double\SqsClientDouble;

/**
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 */
class AmazonSqsDriverEmptyReceiveTest extends TestCase
{
    public function testReceiveWithoutMessagesKeepsPolling(): void
    {
        $body = new MessageSerializer()->serialize(new Message('type', [], 'm1'));
        $responses = [
            new Result([]),
            new Result(['Messages' => []]),
            new Result(['Messages' => [['ReceiptHandle' => 'rh-1', 'Body' => $body]]]),
        ];
        $client = $this->getMockBuilder(SqsClientDouble::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueue', 'receiveMessage', 'deleteMessage'])
            ->getMock();
        $client->expects(self::once())->method('createQueue')
            ->willReturn(new Result(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));
        $client->expects(self::exactly(3))->method('receiveMessage')
            ->willReturnCallback(static function () use (&$responses): Result {
                $response = array_shift($responses);
                self::assertInstanceOf(Result::class, $response);
                return $response;
            });
        $client->expects(self::once())->method('deleteMessage');

        $driver = new AmazonSqsDriver($client, 'test-queue');
        $driver->setMaxProcessItems(1);

        $received = [];
        $driver->wait(static function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });

        self::assertSame(['m1'], $received);
    }
}
