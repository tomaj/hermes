<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 */
#[AllowMockObjectsWithoutExpectations]
class LazyRabbitMqDriverAckTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];

    /**
     * Runs wait() with a channel that delivers $body once.
     */
    private function deliver(string $body, Closure $callback, bool $durable = true): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->method('channel')->willReturn($channel);

        $channel->expects(self::once())->method('queue_declare')->with('q', false, $durable, false, false);
        $channel->expects(self::once())->method('basic_qos')->with(0, 1, false)->willReturnCallback(function (): null {
            $this->log[] = 'qos';
            return null;
        });
        $channel->method('basic_ack')->willReturnCallback(function (int $tag): null {
            $this->log[] = 'ack ' . $tag;
            return null;
        });
        $channel->method('basic_nack')->willReturnCallback(function (int $tag, bool $multiple, bool $requeue): null {
            $this->log[] = 'nack ' . $tag . ($requeue ? ' requeue' : ' drop');
            return null;
        });
        // One consume per wait(): a broken inner loop would re-consume forever.
        $channel->expects(self::once())->method('basic_consume')->willReturnCallback(
            function (string $queue, string $tag, bool $noLocal, bool $noAck, bool $exclusive, bool $nowait, callable $consumer) use ($channel, $body): string {
                self::assertSame('q', $queue);
                self::assertFalse($noAck, 'messages must be acknowledged manually');
                $this->log[] = 'consume';
                $message = new AMQPMessage($body);
                $message->setChannel($channel);
                $message->setDeliveryInfo(7, false, '', 'q');
                $consumer($message);
                return $tag;
            },
        );
        $channel->method('is_consuming')->willReturn(true);

        $driver = new LazyRabbitMqDriver($connection, 'q', [], 0, 'hermes', $durable);
        $driver->setMaxProcessItems(1);
        $driver->wait($callback);
    }

    private static function body(): string
    {
        return new MessageSerializer()->serialize(new Message('type', [], 'm1'));
    }

    public function testMessageIsAcknowledgedAfterTheHandler(): void
    {
        $this->deliver(self::body(), function (MessageInterface $message): void {
            $this->log[] = 'handle ' . $message->getId();
        });

        self::assertSame(['qos', 'consume', 'handle m1', 'ack 7'], $this->log);
    }

    public function testFailingHandlerRequeuesTheMessage(): void
    {
        try {
            $this->deliver(self::body(), function (): void {
                throw new RuntimeException('handler failed');
            });
            self::fail('Exception expected');
        } catch (RuntimeException $e) {
            self::assertSame('handler failed', $e->getMessage());
        }

        self::assertSame(['qos', 'consume', 'nack 7 requeue'], $this->log);
    }

    public function testUnserializableMessageIsRejectedWithoutRequeue(): void
    {
        try {
            $this->deliver('not json', function (): void {
                self::fail('Callback must not run');
            }, false);
            self::fail('Exception expected');
        } catch (\Throwable $e) {
            self::assertInstanceOf(SerializeException::class, $e);
        }

        self::assertSame(['qos', 'consume', 'nack 7 drop'], $this->log);
    }

    public function testShutdownAfterHandlingStillAcknowledges(): void
    {
        try {
            $this->deliver(self::body(), function (): void {
                throw new ShutdownException('Shutdown');
            });
            self::fail('Exception expected');
        } catch (ShutdownException) {
        }

        self::assertSame(['qos', 'consume', 'ack 7'], $this->log);
    }

    public function testDurableDriverPublishesPersistentMessages(): void
    {
        $published = $this->publish(true, []);
        self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $published->get('delivery_mode'));
    }

    public function testExplicitDeliveryModeIsKept(): void
    {
        $published = $this->publish(true, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT]);
        self::assertSame(AMQPMessage::DELIVERY_MODE_NON_PERSISTENT, $published->get('delivery_mode'));
    }

    public function testNonDurableDriverDoesNotSetDeliveryMode(): void
    {
        $published = $this->publish(false, ['content_type' => 'application/json']);
        self::assertFalse($published->has('delivery_mode'));
        self::assertSame('application/json', $published->get('content_type'));
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function publish(bool $durable, array $properties): AMQPMessage
    {
        $connection = $this->createMock(AbstractConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->method('channel')->willReturn($channel);
        $channel->expects(self::once())->method('queue_declare')->with('q', false, $durable, false, false);
        $published = null;
        $channel->expects(self::once())->method('basic_publish')->willReturnCallback(
            function (AMQPMessage $message, string $exchange, string $routingKey) use (&$published): null {
                self::assertSame('', $exchange);
                self::assertSame('q', $routingKey);
                $published = $message;
                return null;
            },
        );

        $driver = new LazyRabbitMqDriver($connection, 'q', $properties, 0, 'hermes', $durable);
        self::assertTrue($driver->send(new Message('type', [])));
        self::assertInstanceOf(AMQPMessage::class, $published);

        return $published;
    }

    public function testDefaultRefreshIntervalDoesNotSleep(): void
    {
        $connection = $this->createMock(AbstractConnection::class);
        $channel = $this->createMock(AMQPChannel::class);
        $connection->method('channel')->willReturn($channel);
        $channel->method('is_consuming')->willReturn(true);
        $shutdown = $this->createMock(\Tomaj\Hermes\Shutdown\ShutdownInterface::class);
        $shutdown->method('shouldShutdown')->willReturnOnConsecutiveCalls(false, true);

        $driver = new LazyRabbitMqDriver($connection, 'q');
        $driver->setShutdown($shutdown);

        $start = microtime(true);
        try {
            $driver->wait(static function (): void {});
            self::fail('Exception expected');
        } catch (ShutdownException) {
        }
        self::assertLessThan(0.5, microtime(true) - $start);
    }
}
