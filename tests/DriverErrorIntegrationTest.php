<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Test\Logger\TestLogger;

/**
 * A driver failure is logged and leaves handle() (#113).
 * Runs against a real broker: set HERMES_TEST_RABBITMQ (host).
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class DriverErrorIntegrationTest extends TestCase
{
    public function testRabbitMqPreconditionFailedIsLoggedAndRethrown(): void
    {
        $host = IntegrationServices::get('HERMES_TEST_RABBITMQ');
        $queue = 'hermes-it-' . bin2hex(random_bytes(4));
        $config = new AMQPConnectionConfig();
        $config->setHost($host);
        $config->setIsLazy(true);

        // a 5.x style non-durable queue; the 6.0 worker declares it durable
        AMQPConnectionFactory::create($config)->channel()->queue_declare($queue, false, false, false, false);

        $logger = new TestLogger();
        $dispatcher = new Dispatcher(new LazyRabbitMqDriver(AMQPConnectionFactory::create($config), $queue), $logger);

        try {
            $dispatcher->handle();
            self::fail('handle() must rethrow the driver exception');
        } catch (AMQPProtocolChannelException $e) {
            self::assertStringContainsString('PRECONDITION_FAILED', $e->getMessage());
        } finally {
            AMQPConnectionFactory::create($config)->channel()->queue_delete($queue);
        }

        $logs = $logger->getLogs();
        self::assertCount(1, $logs);
        self::assertSame('error', $logs[0]['level']);
        self::assertStringStartsWith(
            'Exiting hermes dispatcher - driver error: ' . AMQPProtocolChannelException::class . ' - PRECONDITION_FAILED',
            (string) $logs[0]['message'],
        );
    }
}
