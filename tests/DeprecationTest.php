<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Aws\Result;
use Aws\Sqs\SqsClient;
use DateTime;
use DateTimeInterface;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Redis;
use Throwable;
use Tomaj\Hermes\Deprecation;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tomaj\Hermes\Test\Driver\DummyDriver;

/**
 * @covers \Tomaj\Hermes\Deprecation
 * @covers \Tomaj\Hermes\Dispatcher
 * @covers \Tomaj\Hermes\Emitter
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 * @covers \Tomaj\Hermes\Driver\ShutdownTrait
 * @covers \Tomaj\Hermes\Handler\RetryTrait
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 */
class DeprecationTest extends TestCase
{
    /** @var string[] */
    private $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        set_error_handler(function (int $level, string $message): bool {
            $this->deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testExactClassesTriggerNothing(): void
    {
        $driver = new DummyDriver([]);
        new Dispatcher($driver);
        new Emitter($driver);
        new Message('job');
        new SharedFileShutdown('/tmp/x');
        new PredisShutdown($this->predis());
        new LazyRabbitMqDriver($this->createMock(AMQPLazyConnection::class), 'q', [], 0, 'hermes', false);
        new AmazonSqsDriver($this->sqs(), 'q');
        new PredisSetDriver($this->predis());
        self::assertSame([], $this->deprecations);
    }

    public function testSubclassesAreReported(): void
    {
        $driver = new DummyDriver([]);
        $objects = [
            Dispatcher::class => new class ($driver) extends Dispatcher {
            },
            Emitter::class => new class ($driver) extends Emitter {
            },
            Message::class => new class ('job') extends Message {
            },
            SharedFileShutdown::class => new class ('/tmp/x') extends SharedFileShutdown {
            },
            PredisShutdown::class => new class ($this->predis()) extends PredisShutdown {
            },
            LazyRabbitMqDriver::class => new class ($this->createMock(AMQPLazyConnection::class), 'q', [], 0, 'hermes', false) extends LazyRabbitMqDriver {
            },
            AmazonSqsDriver::class => new class ($this->sqs(), 'q') extends AmazonSqsDriver {
            },
            PredisSetDriver::class => new class ($this->predis()) extends PredisSetDriver {
            },
        ];

        $expected = [];
        foreach ($objects as $parent => $object) {
            $expected[] = 'Since tomaj/hermes 5.1: ' . Deprecation::name($object) . " extends {$parent}, which is final in 6.0. Wrap it or implement its interface instead.";
        }
        self::assertSame($expected, $this->deprecations);
    }

    /**
     * @requires extension redis
     */
    public function testRedisSubclassesAreReported(): void
    {
        $redis = $this->createMock(Redis::class);
        new RedisSetDriver($redis);
        new RedisShutdown($redis);
        self::assertSame([], $this->deprecations);

        new class ($redis) extends RedisSetDriver {
        };
        new class ($redis) extends RedisShutdown {
        };
        self::assertCount(2, $this->deprecations);
        self::assertStringContainsString('extends ' . RedisSetDriver::class . ', which is final', $this->deprecations[0]);
        self::assertStringContainsString('extends ' . RedisShutdown::class . ', which is final', $this->deprecations[1]);
    }

    public function testShutdownWithDateTimeParametersIsReported(): void
    {
        $shutdown = new class implements ShutdownInterface {
            public function shouldShutdown(DateTime $startTime): bool
            {
                return false;
            }

            public function shutdown(?DateTime $shutdownTime = null): bool
            {
                return true;
            }
        };
        $class = Deprecation::name($shutdown);

        new Dispatcher(new DummyDriver([]), null, $shutdown);

        self::assertSame([
            "Since tomaj/hermes 5.1: {$class}::shouldShutdown() takes DateTime; 6.0 passes DateTimeInterface. Widen the parameter type to DateTimeInterface, which 5.x accepts as well.",
            "Since tomaj/hermes 5.1: {$class}::shutdown() takes DateTime; 6.0 passes DateTimeInterface. Widen the parameter type to DateTimeInterface, which 5.x accepts as well.",
        ], $this->deprecations);
    }

    public function testShutdownSetOnADriverIsChecked(): void
    {
        $driver = new PredisSetDriver($this->predis());
        $driver->setShutdown(new class implements ShutdownInterface {
            public function shouldShutdown(DateTime $startTime): bool
            {
                return false;
            }

            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        });
        self::assertCount(1, $this->deprecations);
        self::assertStringContainsString('::shouldShutdown() takes DateTime;', $this->deprecations[0]);
    }

    public function testShutdownPassedToADriverIsReportedOnce(): void
    {
        new Dispatcher(new PredisSetDriver($this->predis()), null, new class implements ShutdownInterface {
            public function shouldShutdown(DateTime $startTime): bool
            {
                return false;
            }

            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        });
        self::assertCount(1, $this->deprecations);
        self::assertStringContainsString('::shouldShutdown() takes DateTime;', $this->deprecations[0]);
    }

    public function testWidenedShutdownIsNotReported(): void
    {
        new Dispatcher(new DummyDriver([]), null, new class implements ShutdownInterface {
            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                return false;
            }

            /**
             * @param DateTime|null $shutdownTime
             */
            public function shutdown($shutdownTime = null): bool
            {
                return true;
            }
        });
        self::assertSame([], $this->deprecations);
    }

    public function testPredisPriorityOrderThatChangesIsReported(): void
    {
        $driver = $this->predisDriverStoppedAfterOneLoop();
        $driver->setupPriorityQueue('low', 50);
        $driver->setupPriorityQueue('high', 200);
        $this->waitOnce($driver);

        self::assertSame([
            'Since tomaj/hermes 5.1: ' . PredisSetDriver::class . ' polls the priorities in reverse registration order (200, 50, 100); 6.0 polls the highest number first (200, 100, 50).',
        ], $this->deprecations);
    }

    public function testPredisPriorityOrderThatStaysIsNotReported(): void
    {
        $driver = $this->predisDriverStoppedAfterOneLoop();
        // reverse registration order 200, 150, 100 is already the highest first
        $driver->setupPriorityQueue('higher', 150);
        $driver->setupPriorityQueue('highest', 200);
        $this->waitOnce($driver);
        self::assertSame([], $this->deprecations);
    }

    /**
     * @requires extension redis
     */
    public function testRedisPriorityOrderThatChangesIsReported(): void
    {
        $redis = $this->getMockBuilder(Redis::class)->disableOriginalConstructor()->onlyMethods(['zRangeByScore', 'sPop'])->getMock();
        $redis->method('zRangeByScore')->willReturn([]);
        $redis->method('sPop')->willReturn(false);
        $driver = new RedisSetDriver($redis, 'hermes', 0);
        $driver->setShutdown($this->stopAfter(1));
        $driver->setupPriorityQueue('low', 50);
        $driver->setupPriorityQueue('high', 200);
        $this->deprecations = [];
        $this->waitOnce($driver);

        self::assertSame([
            'Since tomaj/hermes 5.1: ' . RedisSetDriver::class . ' polls the priorities in reverse registration order (200, 50, 100); 6.0 polls the highest number first (200, 100, 50).',
        ], $this->deprecations);
    }

    public function testHandlerWithoutTraitOrInterfaceIsReportedAndStillRetried(): void
    {
        $handler = new class implements HandlerInterface {
            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }

            public function canRetry(): bool
            {
                return false;
            }

            public function maxRetry(): int
            {
                return 1;
            }
        };
        $driver = $this->handleOne($handler);

        self::assertSame([
            'Since tomaj/hermes 5.1: ' . Deprecation::name($handler) . ' is retried because it has canRetry() and maxRetry(); 6.0 retries only handlers that implement RetryableHandlerInterface or use RetryTrait.',
        ], $this->deprecations);
        self::assertCount(1, $driver->sent);
    }

    public function testHandlerWithOnlyCanRetryIsNotRetried(): void
    {
        $handler = new class implements HandlerInterface {
            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }

            public function canRetry(): bool
            {
                return true;
            }
        };
        $driver = $this->handleOne($handler);

        self::assertSame([], $this->deprecations);
        self::assertCount(0, $driver->sent);
    }

    public function testHandlerWithOnlyMaxRetryIsNotRetried(): void
    {
        $handler = new class implements HandlerInterface {
            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }

            public function maxRetry(): int
            {
                return 1;
            }
        };
        $driver = $this->handleOne($handler);

        self::assertSame([], $this->deprecations);
        self::assertCount(0, $driver->sent);
    }

    public function testOverriddenCanRetryOfTheTraitIsReported(): void
    {
        $handler = new class implements HandlerInterface {
            use RetryTrait;

            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }

            public function canRetry(): bool
            {
                return false;
            }
        };
        $driver = $this->handleOne($handler);

        self::assertSame([
            'Since tomaj/hermes 5.1: 6.0 calls ' . Deprecation::name($handler) . '::canRetry($message, $exception) and skips the retry when it returns false; 5.x does not call it. Implement RetryableHandlerInterface to get the 6.0 behaviour now.',
        ], $this->deprecations);
        self::assertCount(1, $driver->sent, '5.x still ignores canRetry()');
    }

    public function testTraitWithItsOwnCanRetryIsNotReported(): void
    {
        $driver = $this->handleOne(new class implements HandlerInterface {
            use RetryTrait;

            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }
        });
        self::assertSame([], $this->deprecations);
        self::assertCount(1, $driver->sent);
    }

    public function testTraitInheritedFromAParentIsFound(): void
    {
        $driver = $this->handleOne(new class extends RetryingParent {
            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }
        });
        self::assertSame([], $this->deprecations);
        self::assertCount(1, $driver->sent);
    }

    public function testTraitUsedThroughAnotherTraitBesideAParentIsFound(): void
    {
        $driver = $this->handleOne(new class extends \ArrayObject implements HandlerInterface {
            use NestedRetryTrait;
            use EmptyTrait;

            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }
        });
        self::assertSame([], $this->deprecations);
        self::assertCount(1, $driver->sent);
    }

    public function testNameOfAnonymousAndNamedClasses(): void
    {
        self::assertStringEndsWith('@anonymous', Deprecation::name(new class {
        }));
        self::assertSame(\ArrayObject::class, Deprecation::name(new \ArrayObject()));
    }

    public function testRabbitMqRefreshIntervalDefaultIsUnchanged(): void
    {
        $parameter = (new \ReflectionMethod(LazyRabbitMqDriver::class, '__construct'))->getParameters()[3];
        self::assertSame('refreshInterval', $parameter->getName());
        self::assertSame(0, $parameter->getDefaultValue());
    }

    public function testRetryableHandlerFollowsThe60Rules(): void
    {
        $handler = new class implements RetryableHandlerInterface {
            /** @var array<int, array{string, string}> */
            public $calls = [];

            /** @var bool */
            public $retry = true;

            public function handle(MessageInterface $message): bool
            {
                throw new Exception('fail');
            }

            public function canRetry(MessageInterface $message, Throwable $exception): bool
            {
                $this->calls[] = [$message->getId(), $exception->getMessage()];
                return $this->retry;
            }

            public function maxRetry(): int
            {
                return 1;
            }
        };

        self::assertCount(1, $this->handleOne($handler, new Message('job', null, 'id-1'))->sent);
        self::assertSame([['id-1', 'fail']], $handler->calls);

        $handler->retry = false;
        self::assertCount(0, $this->handleOne($handler, new Message('job', null, 'id-2'))->sent, 'canRetry() false skips it');

        $handler->retry = true;
        self::assertCount(0, $this->handleOne($handler, new Message('job', null, 'id-3', null, null, 1))->sent, 'maxRetry() reached');
        self::assertSame([], $this->deprecations);
    }

    public function testRetryTraitAcceptsTheArgumentsOf60(): void
    {
        $handler = new class {
            use RetryTrait;
        };
        self::assertTrue($handler->canRetry());
        self::assertTrue($handler->canRetry(new Message('job'), new Exception('x')));
        self::assertSame(25, $handler->maxRetry());
    }

    public function testRabbitMqWithoutTheDurableArgumentIsReported(): void
    {
        new LazyRabbitMqDriver($this->createMock(AMQPLazyConnection::class), 'q', [], 0, 'hermes');
        self::assertSame([
            'Since tomaj/hermes 5.1: LazyRabbitMqDriver declares a non-durable queue; 6.0 declares a durable queue and publishes persistent messages unless you pass $durable = false. Pass $durable explicitly: false keeps the queue created by 5.x, true matches the 6.0 default.',
        ], $this->deprecations);
    }

    public function testRabbitMqDurableQueueAndPersistentMessages(): void
    {
        [$connection, $channel, $published] = $this->rabbit('q', true);
        $driver = new LazyRabbitMqDriver($connection, 'q', [], 0, 'hermes', true);
        $driver->send(new Message('job'));
        self::assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $published[0]->get('delivery_mode'));
        unset($channel);
    }

    public function testRabbitMqDurableKeepsAnExplicitDeliveryMode(): void
    {
        [$connection, , $published] = $this->rabbit('q', true);
        $driver = new LazyRabbitMqDriver($connection, 'q', ['delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT], 0, 'hermes', true);
        $driver->send(new Message('job'));
        self::assertSame(AMQPMessage::DELIVERY_MODE_NON_PERSISTENT, $published[0]->get('delivery_mode'));
    }

    public function testRabbitMqNonDurableStaysAsIn50(): void
    {
        [$connection, , $published] = $this->rabbit('q', false);
        $driver = new LazyRabbitMqDriver($connection, 'q', [], 0, 'hermes', false);
        $driver->send(new Message('job'));
        self::assertFalse($published[0]->has('delivery_mode'));
    }

    /**
     * @return array{AMQPLazyConnection, AMQPChannel, \ArrayObject<int, AMQPMessage>}
     */
    private function rabbit(string $queue, bool $durable): array
    {
        $published = new \ArrayObject();
        $channel = $this->getMockBuilder(AMQPChannel::class)->disableOriginalConstructor()->onlyMethods(['queue_declare', 'basic_publish'])->getMock();
        $channel->expects(self::once())->method('queue_declare')->with($queue, false, $durable, false, false);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message) use ($published): void {
            $published[] = $message;
        });
        $connection = $this->getMockBuilder(AMQPLazyConnection::class)->disableOriginalConstructor()->onlyMethods(['channel'])->getMock();
        $connection->method('channel')->willReturn($channel);
        return [$connection, $channel, $published];
    }

    private function handleOne(HandlerInterface $handler, ?MessageInterface $message = null): RecordingDriver
    {
        $driver = new RecordingDriver([$message ?? new Message('job')]);
        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', $handler);
        $dispatcher->handle();
        return $driver;
    }

    private function predisDriverStoppedAfterOneLoop(): PredisSetDriver
    {
        $predis = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->addMethods(['zrangebyscore', 'spop'])->getMock();
        $predis->method('zrangebyscore')->willReturn([]);
        $predis->method('spop')->willReturn(null);
        $driver = new PredisSetDriver($predis, 'hermes', 0);
        $driver->setShutdown($this->stopAfter(1));
        $this->deprecations = [];
        return $driver;
    }

    /**
     * @param PredisSetDriver|RedisSetDriver $driver
     */
    private function waitOnce($driver): void
    {
        try {
            $driver->wait(function (): void {
            });
            self::fail('ShutdownException expected');
        } catch (ShutdownException $e) {
            self::assertInstanceOf(ShutdownException::class, $e);
        }
    }

    private function stopAfter(int $loops): ShutdownInterface
    {
        return new class ($loops) implements ShutdownInterface {
            /** @var int */
            private $loops;

            public function __construct(int $loops)
            {
                $this->loops = $loops;
            }

            public function shouldShutdown(DateTimeInterface $startTime): bool
            {
                return $this->loops-- <= 0;
            }

            public function shutdown(?DateTimeInterface $shutdownTime = null): bool
            {
                return true;
            }
        };
    }

    private function predis(): Client
    {
        return $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
    }

    private function sqs(): SqsClient
    {
        $client = $this->getMockBuilder(SqsClient::class)->disableOriginalConstructor()->addMethods(['createQueue'])->getMock();
        $client->method('createQueue')->willReturn(new Result(['QueueUrl' => 'https://sqs.example/q']));
        return $client;
    }
}
