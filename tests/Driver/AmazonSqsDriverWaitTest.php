<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Result;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Double\SqsClientDouble;

/**
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 */
#[AllowMockObjectsWithoutExpectations]
class AmazonSqsDriverWaitTest extends TestCase
{
    private const string QUEUE_URL = 'https://sqs.region.amazonaws.com/123456789/test-queue';

    /** @var list<string> */
    private array $log = [];

    /**
     * @param list<mixed> $messages
     * @return SqsClientDouble&MockObject
     */
    private function client(array $messages): SqsClientDouble
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueue', 'receiveMessage', 'deleteMessage'])
            ->getMock();
        $client->method('createQueue')->willReturn(new Result(['QueueUrl' => self::QUEUE_URL]));
        $client->method('receiveMessage')->willReturnCallback(static function (array $args) use ($messages): Result {
            self::assertSame(['QueueUrl' => self::QUEUE_URL, 'WaitTimeSeconds' => 1], $args);
            return new Result(['Messages' => $messages]);
        });
        $client->method('deleteMessage')->willReturnCallback(function (array $args): null {
            self::assertSame(self::QUEUE_URL, $args['QueueUrl']);
            $handle = $args['ReceiptHandle'];
            self::assertIsString($handle);
            $this->log[] = 'delete ' . $handle;
            return null;
        });

        return $client;
    }

    private function body(string $id): string
    {
        return new MessageSerializer()->serialize(new Message('type', [], $id));
    }

    public function testMessageIsDeletedOnlyAfterTheHandlerFinished(): void
    {
        $driver = new AmazonSqsDriver($this->client([
            'not-an-array',
            ['Body' => $this->body('no-handle')],
            ['ReceiptHandle' => 'no-body'],
            ['ReceiptHandle' => 'rh-1', 'Body' => $this->body('m1')],
            ['ReceiptHandle' => 'rh-2', 'Body' => $this->body('m2')],
        ]), 'test-queue');
        $driver->setMaxProcessItems(2);

        $driver->wait(function (MessageInterface $message): void {
            $this->log[] = 'handle ' . $message->getId();
        });

        self::assertSame(['handle m1', 'delete rh-1', 'handle m2', 'delete rh-2'], $this->log);
        self::assertSame(2, $driver->processed());
    }

    public function testFailingHandlerKeepsTheMessageInTheQueue(): void
    {
        $driver = new AmazonSqsDriver($this->client([
            ['ReceiptHandle' => 'rh-1', 'Body' => $this->body('m1')],
        ]), 'test-queue');

        try {
            $driver->wait(function (): void {
                throw new RuntimeException('handler failed');
            });
            self::fail('Exception expected');
        } catch (RuntimeException $e) {
            self::assertSame('handler failed', $e->getMessage());
        }

        self::assertSame([], $this->log);
        self::assertSame(0, $driver->processed());
    }

    public function testUnserializableMessageIsNotDeleted(): void
    {
        $driver = new AmazonSqsDriver($this->client([
            ['ReceiptHandle' => 'rh-1', 'Body' => 'not json'],
        ]), 'test-queue');

        try {
            $driver->wait(function (): void {
                self::fail('Callback must not run');
            });
            self::fail('Exception expected');
        } catch (\Throwable $e) {
            self::assertInstanceOf(SerializeException::class, $e);
        }

        self::assertSame([], $this->log);
    }

    public function testShutdownAfterHandlingStillDeletesTheMessage(): void
    {
        $driver = new AmazonSqsDriver($this->client([
            ['ReceiptHandle' => 'rh-1', 'Body' => $this->body('m1')],
            ['ReceiptHandle' => 'rh-2', 'Body' => $this->body('m2')],
        ]), 'test-queue');

        try {
            $driver->wait(function (MessageInterface $message): void {
                $this->log[] = 'handle ' . $message->getId();
                throw new ShutdownException('Shutdown');
            });
            self::fail('Exception expected');
        } catch (ShutdownException) {
        }

        self::assertSame(['handle m1', 'delete rh-1'], $this->log);
    }
}
