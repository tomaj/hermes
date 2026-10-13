<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;

/**
 * Runs against an SQS-compatible endpoint (LocalStack, ElasticMQ, moto). Set HERMES_TEST_SQS to the
 * endpoint (for example http://127.0.0.1:4566) to run it; without it the test is skipped.
 */
#[CoversClass(AmazonSqsDriver::class)]
class AmazonSqsDriverIntegrationTest extends TestCase
{
    private SqsClient $client;

    private string $queueName;

    #[\Override]
    protected function setUp(): void
    {
        $endpoint = getenv('HERMES_TEST_SQS');
        if ($endpoint === false || $endpoint === '') {
            self::markTestSkipped('Set HERMES_TEST_SQS to run the SQS integration tests.');
        }
        $this->client = new SqsClient([
            'region' => 'us-east-1',
            'version' => '2012-11-05',
            'endpoint' => $endpoint,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
        $this->queueName = 'hermes-it-' . bin2hex(random_bytes(4));
    }

    public function testFailedMessageIsRedeliveredAndHandledMessageIsDeleted(): void
    {
        $driver = new AmazonSqsDriver($this->client, $this->queueName, ['VisibilityTimeout' => 1]);
        $driver->send(new Message('job', [], 'sqs-1'));

        try {
            $driver->wait(function (): void {
                throw new RuntimeException('handler failed');
            });
        } catch (\Throwable $e) {
            self::assertInstanceOf(RuntimeException::class, $e);
        }

        sleep(2);
        $driver->setMaxProcessItems(1);
        $ids = [];
        $driver->wait(function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });
        self::assertSame(['sqs-1'], $ids);

        sleep(2);
        $queueUrl = $this->client->getQueueUrl(['QueueName' => $this->queueName])->get('QueueUrl');
        self::assertIsString($queueUrl);
        $left = $this->client->receiveMessage(['QueueUrl' => $queueUrl, 'WaitTimeSeconds' => 1])->get('Messages');
        self::assertEmpty($left);
    }
}
