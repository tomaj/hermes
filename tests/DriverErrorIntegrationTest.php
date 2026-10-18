<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Wire\AMQPTable;
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

        // A durable queue that someone declared with an argument the driver does not send. The driver
        // redeclares it without arguments, so RabbitMQ answers PRECONDITION_FAILED (inequivalent arg).
        // A 5.x style non-durable queue would show the same, but RabbitMQ 4.3 no longer lets a client
        // create one by default (deprecated feature transient_nonexcl_queues).
        AMQPConnectionFactory::create($config)->channel()->queue_declare($queue, false, true, false, false, false, new AMQPTable(['x-max-length' => 10]));

        $logger = new TestLogger();
        $dispatcher = new Dispatcher(new LazyRabbitMqDriver(AMQPConnectionFactory::create($config), $queue), $logger);

        try {
            $dispatcher->handle();
            self::fail('handle() must rethrow the driver exception');
        } catch (AMQPProtocolChannelException $e) {
            self::assertStringContainsString('PRECONDITION_FAILED', $e->getMessage());
            self::assertStringContainsString("inequivalent arg 'x-max-length'", $e->getMessage());
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
