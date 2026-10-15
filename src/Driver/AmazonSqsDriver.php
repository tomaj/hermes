<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Aws\Sqs\SqsClient;
use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

final class AmazonSqsDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    private readonly string $queueUrl;

    private int $sleepInterval = 0;

    /**
     * Create new Amazon SQS driver.
     *
     * You have to create aws client instnace and provide it to this driver.
     * You can use service builder or factory method.
     *
     * <code>
     *  use Aws\Sqs\SqsClient;
     *
     *  $client = SqsClient::factory(array(
     *    'profile' => '<profile in your aws credentials file>',
     *    'region'  => '<region name>'
     *  ));
     * </code>
     *
     * or
     *
     * <code>
     * use Aws\Common\Aws;
     *
     * // Create a service builder using a configuration file
     * $aws = Aws::factory('/path/to/my_config.json');
     *
     * // Get the client from the builder by namespace
     * $client = $aws->get('Sqs');
     * </code>
     *
     * More examples see: https://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-sqs.html
     *
     * @see examples/sqs folder
     *
     * @param array<string, scalar|null> $queueAttributes
     */
    public function __construct(
        private readonly SqsClient $client,
        private readonly string $queueName,
        array $queueAttributes = [],
    ) {
        $this->serializer = new MessageSerializer();

        $attributes = array_map(
            static fn(mixed $attributeValue): string => is_scalar($attributeValue) ? (string) $attributeValue : '',
            $queueAttributes,
        );

        $result = $this->client->createQueue([
            'QueueName' => $this->queueName,
            'Attributes' => $attributes,
        ]);

        $queueUrl = $result->get('QueueUrl');
        if (!is_string($queueUrl)) {
            throw new \RuntimeException('Amazon SQS createQueue response did not include a queue URL');
        }
        $this->queueUrl = $queueUrl;
    }

    #[\Override]
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $this->serializer->serialize($message),
        ]);
        return true;
    }

    /**
     * @throws NotSupportedException
     */
    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): never
    {
        throw new NotSupportedException('AmazonSQS is not supporting priority queues now');
    }

    #[\Override]
    public function wait(Closure $callback, array $priorities = []): void
    {
        while (true) {
            $this->checkShutdown();
            if (!$this->shouldProcessNext()) {
                break;
            }

            $result = $this->client->receiveMessage([
                'QueueUrl' => $this->queueUrl,
                'WaitTimeSeconds' => 1,
            ]);

            $messages = $result['Messages'] ?? null;

            if (is_array($messages) && $messages !== []) {
                foreach ($messages as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $receiptHandle = $message['ReceiptHandle'] ?? null;
                    $body = $message['Body'] ?? null;
                    if (!is_string($receiptHandle) || !is_string($body)) {
                        continue;
                    }
                    // Delete only after the message was handled. If unserializing or the callback fails,
                    // the message stays in SQS and is redelivered after the visibility timeout (or moved to a DLQ).
                    try {
                        $callback($this->serializer->unserialize($body));
                    } catch (ShutdownException $e) {
                        // The dispatcher requests shutdown after the message was handled.
                        $this->deleteMessage($receiptHandle);
                        throw $e;
                    }
                    $this->deleteMessage($receiptHandle);
                    $this->incrementProcessedItems();
                }
            } elseif ($this->sleepInterval !== 0) {
                $this->checkShutdown();
                sleep($this->sleepInterval);
            }
        }
    }

    private function deleteMessage(string $receiptHandle): void
    {
        $this->client->deleteMessage([
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $receiptHandle,
        ]);
    }
}
