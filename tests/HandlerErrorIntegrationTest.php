<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Aws\Sqs\SqsClient;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use TypeError;

/**
 * A handler that throws a PHP Error is a failed message, not a crashed worker (#112).
 * Runs against real brokers: set HERMES_TEST_RABBITMQ (host) and HERMES_TEST_SQS (endpoint).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class HandlerErrorIntegrationTest extends TestCase
{
    /**
     * @return array{0: HandlerInterface, 1: \ArrayObject<int, string>}
     */
    private function poisonHandler(): array
    {
        $seen = new \ArrayObject();
        $handler = new readonly class ($seen) implements HandlerInterface {
            /** @param \ArrayObject<int, string> $seen */
            public function __construct(private \ArrayObject $seen) {}

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                $this->seen[] = $message->getId();
                if ($message->getId() === 'poison') {
                    throw new TypeError('poison payload');
                }

                return true;
            }
        };

        return [$handler, $seen];
    }

    private function runWorker(DriverInterface $driver, HandlerInterface $handler): void
    {
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', $handler);
        $dispatcher->handle();
    }

    public function testRabbitMqWorkerSurvivesAnErrorAndAcknowledgesTheMessage(): void
    {
        $host = getenv('HERMES_TEST_RABBITMQ');
        if ($host === false || $host === '') {
            self::markTestSkipped('Set HERMES_TEST_RABBITMQ to run the RabbitMQ integration tests.');
        }
        $queue = 'hermes-it-' . bin2hex(random_bytes(4));
        $config = new AMQPConnectionConfig();
        $config->setHost($host);
        $config->setIsLazy(true);

        new LazyRabbitMqDriver(AMQPConnectionFactory::create($config), $queue)->send(new Message('job', [], 'poison'));
        new LazyRabbitMqDriver(AMQPConnectionFactory::create($config), $queue)->send(new Message('job', [], 'ok'));

        [$handler, $seen] = $this->poisonHandler();
        $driver = new LazyRabbitMqDriver(AMQPConnectionFactory::create($config), $queue);
        $driver->setMaxProcessItems(2);
        $this->runWorker($driver, $handler);

        self::assertSame(['poison', 'ok'], $seen->getArrayCopy());
        $channel = AMQPConnectionFactory::create($config)->channel();
        $declared = $channel->queue_declare($queue, true);
        self::assertIsArray($declared);
        self::assertSame(0, $declared[1], 'the failed message is acknowledged, not requeued');
        $channel->queue_delete($queue);
    }

    public function testSqsWorkerSurvivesAnErrorAndDeletesTheMessage(): void
    {
        $endpoint = getenv('HERMES_TEST_SQS');
        if ($endpoint === false || $endpoint === '') {
            self::markTestSkipped('Set HERMES_TEST_SQS to run the SQS integration tests.');
        }
        $client = new SqsClient([
            'region' => 'us-east-1',
            'version' => '2012-11-05',
            'endpoint' => $endpoint,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
        $queue = 'hermes-it-' . bin2hex(random_bytes(4));
        $driver = new AmazonSqsDriver($client, $queue, ['VisibilityTimeout' => 1]);
        $driver->send(new Message('job', [], 'poison'));
        $driver->send(new Message('job', [], 'ok'));

        [$handler, $seen] = $this->poisonHandler();
        $driver->setMaxProcessItems(2);
        $this->runWorker($driver, $handler);

        self::assertSame(['ok', 'poison'], $this->sorted($seen->getArrayCopy()));
        sleep(2); // past the visibility timeout: an undeleted message would be visible again
        $queueUrl = $client->getQueueUrl(['QueueName' => $queue])->get('QueueUrl');
        self::assertIsString($queueUrl);
        $left = $client->receiveMessage(['QueueUrl' => $queueUrl, 'WaitTimeSeconds' => 1]);
        self::assertSame([], $left['Messages'] ?? [], 'the failed message is deleted, not redelivered');
        $client->deleteQueue(['QueueUrl' => $queueUrl]);
    }

    /**
     * @param array<int, string> $ids
     * @return list<string>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}
