<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\KafkaDriver;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\IntegrationServices;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * Runs against a real Kafka broker. Set HERMES_TEST_KAFKA to its bootstrap servers (for example
 * 127.0.0.1:9092) to run it; without it the test is skipped.
 */
#[CoversClass(KafkaDriver::class)]
#[RequiresPhpExtension('rdkafka')]
final class KafkaDriverIntegrationTest extends TestCase
{
    private string $brokers;

    private string $topic;

    private string $group;

    #[\Override]
    protected function setUp(): void
    {
        $this->brokers = IntegrationServices::get('HERMES_TEST_KAFKA');
        $this->topic = 'hermes-it-' . bin2hex(random_bytes(4));
        $this->group = "{$this->topic}-workers";
    }

    public function testMessagesArriveInOrderAndCommittedOnesAreNotDeliveredAgain(): void
    {
        $emitter = new KafkaDriver($this->producer(), null, $this->topic);
        foreach (['k-1', 'k-2', 'k-3'] as $id) {
            $emitter->send(new Message('job', ['id' => $id], $id));
        }

        self::assertSame(['k-1', 'k-2'], $this->receive(2));
        $emitter->send(new Message('job', null, 'k-4'));
        // A new worker of the group continues after the last committed message.
        self::assertSame(['k-3', 'k-4'], $this->receive(2));
    }

    public function testFailedMessageIsDeliveredAgainToTheNextWorker(): void
    {
        new KafkaDriver($this->producer(), null, $this->topic)->send(new Message('job', null, 'k-1'));

        $driver = $this->worker();
        try {
            $driver->wait(static function (): never {
                throw new RuntimeException('worker crashed');
            });
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame('worker crashed', $e->getMessage());
        }
        // The crashed worker leaves the group, like a process that exits.
        unset($driver);

        self::assertSame(['k-1'], $this->receive(1));
    }

    public function testDispatcherRetryIsSentAgainToTheTopic(): void
    {
        new KafkaDriver($this->producer(), null, $this->topic)->send(new Message('job', null, 'k-1'));

        $driver = $this->worker();
        $driver->setMaxProcessItems(2);
        $dispatcher = new Dispatcher($driver);
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            /** @var list<int> */
            public array $retries = [];

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                $this->retries[] = $message->getRetries();
                if ($message->getRetries() === 0) {
                    throw new RuntimeException('first attempt fails');
                }
                return true;
            }
        };
        $dispatcher->registerHandler('job', $handler);
        $dispatcher->handle();

        self::assertSame([0, 1], $handler->retries);
    }

    /**
     * @return list<string>
     */
    private function receive(int $count): array
    {
        $driver = $this->worker();
        $driver->setMaxProcessItems($count);
        $ids = [];
        $driver->wait(static function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });
        return $ids;
    }

    private function worker(): KafkaDriver
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', $this->brokers);
        $conf->set('group.id', $this->group);
        $conf->set('enable.auto.commit', 'false');
        $conf->set('auto.offset.reset', 'earliest');
        $driver = new KafkaDriver($this->producer(), new KafkaConsumer($conf), $this->topic, 200);
        $driver->setShutdown(new PollBudgetShutdown(1000, 8.0));
        return $driver;
    }

    private function producer(): Producer
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', $this->brokers);
        return new Producer($conf);
    }
}
