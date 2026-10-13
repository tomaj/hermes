<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Shutdown\RedisShutdown;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use Tomaj\Hermes\Test\Double\PredisClientDouble;
use Tomaj\Hermes\Test\Driver\DummyDriver;

/**
 * Pins public behaviour that constructor promotion and readonly properties must not change:
 * constructor signatures (types, names, order, defaults), the typed DEFAULT_PRIORITY constant, Message defaults, fluent return
 * types, and unserializing a Message that Hermes 5.x serialized.
 */
#[CoversClass(Message::class)]
#[CoversClass(Dispatcher::class)]
#[CoversClass(Emitter::class)]
#[CoversClass(RedisSetDriver::class)]
#[CoversClass(PredisSetDriver::class)]
#[CoversClass(LazyRabbitMqDriver::class)]
#[CoversClass(AmazonSqsDriver::class)]
#[CoversClass(RedisShutdown::class)]
#[CoversClass(PredisShutdown::class)]
#[CoversClass(SharedFileShutdown::class)]
final class PublicApiContractTest extends TestCase
{
    /**
     * Parameter lists as on Hermes 5.x master: type, name and default, in order.
     *
     * @return iterable<string, array{class-string, list<string>}>
     */
    public static function constructors(): iterable
    {
        yield 'Message' => [Message::class, ['string $type', '?array $payload = null', '?string $messageId = null', '?float $created = null', '?float $executeAt = null', 'int $retries = 0']];
        yield 'Dispatcher' => [Dispatcher::class, ['Tomaj\\Hermes\\Driver\\DriverInterface $driver', '?Psr\\Log\\LoggerInterface $logger = null', '?Tomaj\\Hermes\\Shutdown\\ShutdownInterface $shutdown = null', '?Random\\Randomizer $randomizer = null']];
        yield 'Emitter' => [Emitter::class, ['Tomaj\\Hermes\\Driver\\DriverInterface $driver', '?Psr\\Log\\LoggerInterface $logger = null']];
        yield 'RedisSetDriver' => [RedisSetDriver::class, ['Redis $redis', 'string $key = "hermes"', 'int $refreshInterval = 1', 'string $scheduleKey = "hermes_schedule"']];
        yield 'PredisSetDriver' => [PredisSetDriver::class, ['Predis\\Client $redis', 'string $key = "hermes"', 'int $refreshInterval = 1', 'string $scheduleKey = "hermes_schedule"']];
        yield 'LazyRabbitMqDriver' => [LazyRabbitMqDriver::class, ['PhpAmqpLib\\Connection\\AMQPLazyConnection $connection', 'string $queue', 'array $amqpMessageProperties = []', 'int $refreshInterval = 0', 'string $consumerTag = "hermes"', 'bool $durable = true']];
        yield 'AmazonSqsDriver' => [AmazonSqsDriver::class, ['Aws\\Sqs\\SqsClient $client', 'string $queueName', 'array $queueAttributes = []']];
        yield 'RedisShutdown' => [RedisShutdown::class, ['Redis $redis', 'string $key = "hermes_shutdown"']];
        yield 'PredisShutdown' => [PredisShutdown::class, ['Predis\\Client $redis', 'string $key = "hermes_shutdown"']];
        yield 'SharedFileShutdown' => [SharedFileShutdown::class, ['string $filePath']];
    }

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('constructors')]
    public function testConstructorSignatureIsUnchanged(string $class, array $expected): void
    {
        $actual = [];
        foreach (new ReflectionMethod($class, '__construct')->getParameters() as $parameter) {
            $signature = "{$parameter->getType()} \${$parameter->getName()}";
            if ($parameter->isDefaultValueAvailable()) {
                $signature .= ' = ' . json_encode($parameter->getDefaultValue(), JSON_THROW_ON_ERROR);
            }
            $actual[] = $signature;
        }
        self::assertSame($expected, $actual);
    }

    /**
     * Constructs the Redis drivers with their defaults, so mutation testing runs this test
     * against the promoted constructor lines, and checks the default refresh interval.
     */
    #[RequiresPhpExtension('redis')]
    public function testRedisDriversDefaultToOneSecondRefreshInterval(): void
    {
        $redis = new RedisSetDriver(self::createStub(Redis::class));
        $predis = new PredisSetDriver(self::createStub(PredisClientDouble::class));

        self::assertSame(1, new ReflectionProperty($redis, 'refreshInterval')->getValue($redis));
        self::assertSame(1, new ReflectionProperty($predis, 'refreshInterval')->getValue($predis));
        self::assertSame('hermes_schedule', new ReflectionProperty($redis, 'scheduleKey')->getValue($redis));
        self::assertSame('hermes_schedule', new ReflectionProperty($predis, 'scheduleKey')->getValue($predis));
    }

    public function testDefaultPriorityIsATypedIntConstant(): void
    {
        $constant = new ReflectionClassConstant(Dispatcher::class, 'DEFAULT_PRIORITY');
        $type = $constant->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame('int', $type->getName());
        self::assertSame(100, $constant->getValue());
    }

    public function testMessageDefaults(): void
    {
        $before = microtime(true);
        $message = new Message('type');

        self::assertSame('type', $message->getType());
        self::assertNull($message->getPayload());
        self::assertNull($message->getExecuteAt());
        self::assertSame(0, $message->getRetries());
        self::assertGreaterThanOrEqual($before, $message->getCreated());
        self::assertLessThanOrEqual(microtime(true), $message->getCreated());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $message->getId());
    }

    public function testMessageSerializedByHermes5StillUnserializes(): void
    {
        // serialize(new Message('t', ['a' => 1], 'id', 1.5, 2.5, 3)) on Hermes 5.x, before the properties were promoted
        $message = unserialize(
            "O:20:\"Tomaj\\Hermes\\Message\":6:{s:26:\"\0Tomaj\\Hermes\\Message\0type\";s:1:\"t\";s:29:\"\0Tomaj\\Hermes\\Message\0payload\";a:1:{s:1:\"a\";i:1;}s:31:\"\0Tomaj\\Hermes\\Message\0messageId\";s:2:\"id\";s:29:\"\0Tomaj\\Hermes\\Message\0created\";d:1.5;s:31:\"\0Tomaj\\Hermes\\Message\0executeAt\";d:2.5;s:29:\"\0Tomaj\\Hermes\\Message\0retries\";i:3;}",
            ['allowed_classes' => [Message::class]],
        );

        self::assertInstanceOf(Message::class, $message);
        self::assertEquals(new Message('t', ['a' => 1], 'id', 1.5, 2.5, 3), $message);
        self::assertSame(3, $message->getRetries());
    }

    public function testFluentMethodsReturnTheSameConcreteInstance(): void
    {
        $dispatcher = new Dispatcher(new DummyDriver());
        self::assertSame($dispatcher, $this->dispatcher($dispatcher->registerHandler('a', new EchoHandler())));
        self::assertSame($dispatcher, $this->dispatcher($dispatcher->emit(new Message('a'))));

        $emitter = new Emitter(new DummyDriver());
        self::assertSame($emitter, $this->emitter($emitter->emit(new Message('a'))));
    }

    /**
     * Accepts only the concrete class, so PHPStan fails when the `@return $this` contract is lost.
     */
    private function dispatcher(Dispatcher $dispatcher): Dispatcher
    {
        return $dispatcher;
    }

    private function emitter(Emitter $emitter): Emitter
    {
        return $emitter;
    }
}
