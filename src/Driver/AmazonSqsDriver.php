<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Aws\Sqs\SqsClient;

class AmazonSqsDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    /**
     * @var SqsClient
     */
    private $client;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var string
     */
    private $queueUrl;

    /**
     * @var integer
     */
    private $sleepInterval = 0;

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
     *
     * @see examples/sqs folder
     *
     * @param SqsClient     $client
     * @param string        $queueName
     * @param array<mixed>  $queueAttributes
     */
    public function __construct(SqsClient $client, string $queueName, array $queueAttributes = [])
    {
        $this->client = $client;
        $this->queueName = $queueName;
        $this->serializer = new MessageSerializer();

        $result = $client->createQueue([
            'QueueName' => $queueName,
            'Attributes' => $queueAttributes,
        ]);

        $this->queueUrl = $result->get('QueueUrl');
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $this->serializer->serialize($message),
        ]);
        return true;
    }

    /**
     * @param string $name
     * @param int $priority
     *
     * @throws NotSupportedException
     */
    public function setupPriorityQueue(string $name, int $priority): void
    {
        throw new NotSupportedException("AmazonSQS is not supporting priority queues now");
    }

    /**
     * {@inheritdoc}
     */
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

            $messages = $result['Messages'];

            if ($messages) {
                $hermesMessages = [];
                foreach ($messages as $message) {
                    $this->client->deleteMessage([
                        'QueueUrl' => $this->queueUrl,
                        'ReceiptHandle' => $message['ReceiptHandle'],
                    ]);
                    $hermesMessages[] = $this->serializer->unserialize($message['Body']);
                }
                foreach ($hermesMessages as $hermesMessage) {
                    $callback($hermesMessage);
                    $this->incrementProcessedItems();
                }
            } else {
                if ($this->sleepInterval) {
                    $this->checkShutdown();
                    sleep($this->sleepInterval);
                }
            }
        }
    }
}
