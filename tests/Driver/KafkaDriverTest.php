<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RdKafka\KafkaConsumer;
use RdKafka\Message as KafkaMessage;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use ReflectionProperty;
use RuntimeException;
use Tomaj\Hermes\Driver\KafkaDriver;
use Tomaj\Hermes\Driver\KafkaDriverException;
use Tomaj\Hermes\Driver\NotSupportedException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

#[CoversClass(KafkaDriver::class)]
#[RequiresPhpExtension('rdkafka')]
final class KafkaDriverTest extends TestCase
{
    public function testSendProducesKeyedByIdAndWaitsForTheConfirmation(): void
    {
        $message = new Message('job', ['a' => 1], 'id-1', 1700000000.0);
        $topic = $this->createMock(ProducerTopic::class);
        $topic->expects(self::once())->method('produce')
            ->with(\RD_KAFKA_PARTITION_UA, 0, new MessageSerializer()->serialize($message), 'id-1');
        $producer = $this->createMock(Producer::class);
        $producer->expects(self::once())->method('newTopic')->with('jobs')->willReturn($topic);
        $producer->expects(self::once())->method('flush')->with(2500)->willReturn(\RD_KAFKA_RESP_ERR_NO_ERROR);

        self::assertTrue(new KafkaDriver($producer, null, 'jobs', 1000, 2500)->send($message));
    }

    public function testSendThrowsWhenKafkaDoesNotConfirm(): void
    {
        $producer = self::createStub(Producer::class);
        $producer->method('newTopic')->willReturn(self::createStub(ProducerTopic::class));
        $producer->method('flush')->willReturn(\RD_KAFKA_RESP_ERR__TIMED_OUT);

        try {
            new KafkaDriver($producer, null, 'jobs')->send(new Message('job', null, 'id-1'));
            self::fail('KafkaDriverException expected');
        } catch (KafkaDriverException $e) {
            self::assertSame('Kafka did not confirm message id-1 on topic jobs: Local: Timed out', $e->getMessage());
            self::assertSame(\RD_KAFKA_RESP_ERR__TIMED_OUT, $e->getCode());
        }
    }

    public function testDefaults(): void
    {
        $driver = new KafkaDriver(self::createStub(Producer::class));
        self::assertNull($this->property($driver, 'consumer'));
        self::assertSame('hermes', $this->property($driver, 'topic'));
        self::assertSame(1000, $this->property($driver, 'consumeTimeout'));
        self::assertSame(10000, $this->property($driver, 'flushTimeout'));
    }

    public function testSmallestTimeoutsAreAccepted(): void
    {
        $driver = new KafkaDriver(self::createStub(Producer::class), null, 't', 1, 1);
        self::assertSame(1, $this->property($driver, 'consumeTimeout'));
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'empty topic' => ['', 1, 1, 'The Kafka topic must not be empty'];
        yield 'zero consume timeout' => ['t', 0, 1, 'consumeTimeout and flushTimeout must be positive'];
        yield 'negative consume timeout' => ['t', -1, 1, 'consumeTimeout and flushTimeout must be positive'];
        yield 'zero flush timeout' => ['t', 1, 0, 'consumeTimeout and flushTimeout must be positive'];
        yield 'negative flush timeout' => ['t', 1, -1, 'consumeTimeout and flushTimeout must be positive'];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsAreRejected(string $topic, int $consume, int $flush, string $error): void
    {
        $this->assertThrows(InvalidArgumentException::class, $error, fn(): KafkaDriver => new KafkaDriver(self::createStub(Producer::class), null, $topic, $consume, $flush));
    }

    public function testPriorityQueuesAreNotSupported(): void
    {
        $this->assertThrows(NotSupportedException::class, 'Kafka is not supporting priority queues now', fn() => new KafkaDriver(self::createStub(Producer::class))->setupPriorityQueue('high', 200));
    }

    public function testWaitNeedsAConsumer(): void
    {
        $this->assertThrows(KafkaDriverException::class, 'KafkaDriver needs a KafkaConsumer to wait for messages', fn() => new KafkaDriver(self::createStub(Producer::class))->wait(static function (): void {}));
    }

    public function testHandledMessagesAreCommittedAfterTheCallback(): void
    {
        $first = $this->kafkaMessage(new Message('job', null, 'a'));
        $second = $this->kafkaMessage(new Message('job', null, 'b'));
        $log = [];
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::once())->method('subscribe')->with(['jobs']);
        $consumer->expects(self::exactly(4))->method('consume')->with(700)->willReturnOnConsecutiveCalls(
            $this->error(\RD_KAFKA_RESP_ERR__TIMED_OUT),
            $first,
            $this->error(\RD_KAFKA_RESP_ERR__PARTITION_EOF),
            $second,
        );
        $consumer->expects(self::exactly(2))->method('commit')->willReturnCallback(static function (KafkaMessage $message) use (&$log): void {
            $log[] = "commit {$message->key}";
        });

        $driver = new KafkaDriver(self::createStub(Producer::class), $consumer, 'jobs', 700);
        $driver->setMaxProcessItems(2);
        $driver->setShutdown(new PollBudgetShutdown());
        $driver->wait(static function (MessageInterface $message) use (&$log): void {
            $log[] = "handle {$message->getId()}";
        });

        self::assertSame(['handle a', 'commit a', 'handle b', 'commit b'], $log);
        self::assertSame(2, $driver->processed());
    }

    public function testSubscribesOnlyOnce(): void
    {
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::once())->method('subscribe');
        $consumer->method('consume')->willReturnCallback(fn(): KafkaMessage => $this->kafkaMessage(new Message('job')));

        $driver = new KafkaDriver(self::createStub(Producer::class), $consumer);
        $driver->setShutdown(new PollBudgetShutdown());
        $driver->setMaxProcessItems(1);
        $driver->wait(static function (): void {});
        $driver->setMaxProcessItems(2);
        $driver->wait(static function (): void {});
        self::assertSame(2, $driver->processed());
    }

    public function testConsumerErrorStopsTheWorker(): void
    {
        $error = $this->error(\RD_KAFKA_RESP_ERR__TRANSPORT);
        $consumer = self::createStub(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($error);

        try {
            new KafkaDriver(self::createStub(Producer::class), $consumer, 'jobs')->wait(static function (): void {});
            self::fail('KafkaDriverException expected');
        } catch (KafkaDriverException $e) {
            self::assertSame("Kafka consumer error on topic jobs: {$error->errstr()}", $e->getMessage());
            self::assertSame(\RD_KAFKA_RESP_ERR__TRANSPORT, $e->getCode());
        }
    }

    public function testFailingCallbackLeavesTheOffsetUncommitted(): void
    {
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($this->kafkaMessage(new Message('job')));
        $consumer->expects(self::never())->method('commit');
        $driver = new KafkaDriver(self::createStub(Producer::class), $consumer);

        try {
            $driver->wait(static function (): never {
                throw new RuntimeException('broken');
            });
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame('broken', $e->getMessage());
        }
        self::assertSame(0, $driver->processed());
    }

    public function testShutdownFromTheCallbackCommitsTheHandledMessage(): void
    {
        $message = $this->kafkaMessage(new Message('job'));
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($message);
        $consumer->expects(self::once())->method('commit')->with($message);

        $this->expectException(ShutdownException::class);
        new KafkaDriver(self::createStub(Producer::class), $consumer)->wait(static function (): never {
            throw new ShutdownException('stop');
        });
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unreadablePayloads(): iterable
    {
        yield 'not json' => ['{broken'];
        yield 'tombstone' => [null];
    }

    #[DataProvider('unreadablePayloads')]
    public function testUnreadableMessageIsCommittedAndReported(?string $payload): void
    {
        $message = $this->error(\RD_KAFKA_RESP_ERR_NO_ERROR);
        $message->payload = $payload;
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->method('consume')->willReturn($message);
        $consumer->expects(self::once())->method('commit')->with($message);

        $this->expectException(SerializeException::class);
        new KafkaDriver(self::createStub(Producer::class), $consumer)->wait(static function (): never {
            self::fail('the callback must not run');
        });
    }

    public function testShutdownIsCheckedBeforeConsuming(): void
    {
        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects(self::exactly(2))->method('consume')->willReturn($this->error(\RD_KAFKA_RESP_ERR__TIMED_OUT));
        $driver = new KafkaDriver(self::createStub(Producer::class), $consumer);
        $driver->setShutdown(new PollBudgetShutdown(2));

        $this->expectException(ShutdownException::class);
        $driver->wait(static function (): void {});
    }

    /**
     * @param class-string<\Throwable> $class
     */
    private function assertThrows(string $class, string $message, \Closure $code): void
    {
        try {
            $code();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());
            return;
        }
        self::fail("{$class} expected");
    }

    private function kafkaMessage(MessageInterface $message): KafkaMessage
    {
        $kafkaMessage = $this->error(\RD_KAFKA_RESP_ERR_NO_ERROR);
        $kafkaMessage->payload = new MessageSerializer()->serialize($message);
        $kafkaMessage->key = $message->getId();
        return $kafkaMessage;
    }

    private function error(int $code): KafkaMessage
    {
        $message = new KafkaMessage();
        $message->err = $code;
        return $message;
    }

    private function property(KafkaDriver $driver, string $name): mixed
    {
        return new ReflectionProperty($driver, $name)->getValue($driver);
    }
}
