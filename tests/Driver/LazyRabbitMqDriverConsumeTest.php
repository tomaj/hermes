<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;

/**
 * The consumer LazyRabbitMqDriver registers, and what it closes when it stops.
 *
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 */
#[AllowMockObjectsWithoutExpectations]
class LazyRabbitMqDriverConsumeTest extends TestCase
{
    public function testConsumesWithManualAckAndClosesChannelAndConnection(): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->method('channel')->willReturn($channel);

        $channel->expects(self::once())->method('basic_consume')->willReturnCallback(
            static function (string $queue, string $tag, bool $noLocal, bool $noAck, bool $exclusive, bool $nowait, callable $consumer) use ($channel): string {
                self::assertSame(['q', 'worker-1'], [$queue, $tag]);
                // no_local, no_ack, exclusive, nowait: shared queue, manual ack, wait for consume-ok
                self::assertSame([false, false, false, false], [$noLocal, $noAck, $exclusive, $nowait]);
                $message = new AMQPMessage(new MessageSerializer()->serialize(new Message('type', [], 'm1')));
                $message->setChannel($channel);
                $message->setDeliveryInfo(1, false, '', 'q');
                $consumer($message);
                return $tag;
            },
        );
        $channel->method('is_consuming')->willReturn(true);
        $channel->expects(self::once())->method('close');
        $connection->expects(self::once())->method('close');

        $driver = new LazyRabbitMqDriver($connection, 'q', [], 0, 'worker-1');
        $driver->setMaxProcessItems(1);

        $received = [];
        $driver->wait(static function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });

        self::assertSame(['m1'], $received);
    }
}
