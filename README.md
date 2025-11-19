# Hermes

**Background job processing PHP library**

[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/hermes.svg)](https://packagist.org/packages/tomaj/hermes)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)

## What is Hermes?

Hermes is a lightweight PHP library for background job processing. When you need to handle time-consuming tasks outside of HTTP requests—such as sending emails, calling external APIs, or processing data—Hermes provides a clean, efficient solution.

Key features:
- **Multiple queue backends**: Support for Redis, RabbitMQ, Amazon SQS, and more
- **Simple integration**: Easy to add to existing projects with minimal setup
- **Extensible architecture**: Create custom drivers and handlers for your specific needs
- **Production-ready**: Built-in support for priorities, retries, and graceful shutdown


## Installation

This library requires PHP 7.4 or later.

The recommended installation method is via Composer:

```bash
$ composer require tomaj/hermes
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Optional Dependencies

Hermes supports logging through any PSR-3 compatible logger. For more information, see [psr/log][].

While the library works without logging, we recommend installing [monolog][] for production environments to track message processing and debugging.

[psr/log]: https://github.com/php-fig/log
[monolog]: https://github.com/Seldaek/monolog

## Supported Drivers

Hermes includes built-in support for multiple queue backends:

 * **[Redis][]** - Two implementations available: [phpredis][] (native extension) or [Predis][] (pure PHP)
 * **[Amazon SQS][]** - AWS Simple Queue Service integration
 * **[RabbitMQ][]** - Industry-standard message broker
 * **[ZeroMQ][]** - Available as a separate package: [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver)

**Note:** You need to install the corresponding client libraries for your chosen driver. For example, to use Redis with Predis, add `predis/predis` to your `composer.json` and configure your Redis connection.

[Amazon SQS]: https://aws.amazon.com/sqs/
[php-zmq]: https://zeromq.org/
[phpredis]: https://github.com/phpredis/phpredis
[Redis]: https://redis.io/
[RabbitMQ]: https://www.rabbitmq.com/
[Predis]: https://github.com/nrk/predis
[ZeroMQ]: https://zeromq.org/


## Concept - How Hermes Works

Hermes acts as a message broker between your web application and background workers. Here's the flow:

```
+--------------------------------------------------------+
|         Web Application (HTTP Request)                |
|                                                        |
|  /file.php --> emit(Message) --> Hermes Emitter       |
+------------------------+-------------------------------+
                         |
                         v
                  +-------------+
                  |    Queue    |
                  | Redis/Rabbit|
                  +------+------+
                         |
                         v
+--------------------------------------------------------+
|        Background Worker (PHP CLI)                    |
|                                                        |
|  Dispatcher --> wait() --> Handler::handle()          |
|                                                        |
|  * Continuously listens for new messages              |
|  * Calls registered handler to process each message   |
+--------------------------------------------------------+
```

Implementation steps:

1. **Choose a driver**: Select a queue backend (Redis, RabbitMQ, etc.) and register it with the Dispatcher and Emitter
2. **Emit messages**: Send messages to the queue when you need background processing
3. **Create handlers**: Write handler classes to process your messages
4. **Run the worker**: Create a PHP CLI script that runs continuously to process messages from the queue


## How to Use

This example demonstrates using the Redis driver to send emails in the background.

### Emitting Messages

Emit messages from anywhere in your application—it's quick and straightforward:

```php
use Redis;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Driver\RedisSetDriver;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$emitter = new Emitter($driver);

$message = new Message('send-email', [
	'to' => 'test@test.com',
	'subject' => 'Testing hermes email',
	'message' => 'Hello from hermes!'
]);

$emitter->emit($message);
```

### Processing Messages

To process messages, create a PHP CLI script that runs continuously. Here's a simple implementation with a handler:


```php
# file handler.php
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;

class SendEmailHandler implements HandlerInterface
{
    // here you will receive message that was emitted from web application
    public function handle(MessageInterface $message)
    {
    	$payload = $message->getPayload();
    	mail($payload['to'], $payload['subject'], $payload['message']);
    	return true;
    }
}


// create dispatcher like in the first snippet
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);

// register handler for event
$dispatcher->registerHandler('send-email', new SendEmailHandler());

// at this point this script will wait for new message
$dispatcher->handle();
```

To keep the worker running continuously on your server, use a process manager like [supervisord][], [upstart][], [monit][], or [god][].

[upstart]: http://upstart.ubuntu.com/
[supervisord]: http://supervisord.org
[monit]: https://mmonit.com/monit/
[god]: http://godrb.com/

## Logging

Hermes supports any PSR-3 compliant logger. Set a logger for the Dispatcher or Emitter to track message flow and handler execution.

To enable logging in your handlers, add the `Psr\Log\LoggerAwareTrait` trait (or implement `Psr\Log\LoggerAwareInterface`)—the Dispatcher and Emitter will automatically inject the logger.

Example using [monolog][]:

```php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('hermes');
$log->pushHandler(new StreamHandler('hermes.log'));

// $driver = ....

$dispatcher = new Dispatcher($driver, $log);
```

To add logging within your handlers:

```php
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Psr\Log\LoggerAwareTrait;

class SendEmailHandlerWithLogger implements HandlerInterface
{
    // enable logger
    use LoggerAwareTrait;

    public function handle(MessageInterface $message)
    {
        $payload = $message->getPayload();

        // log info message
    	$this->logger->info("Trying to send email to {$payload['to']}");

    	mail($payload['to'], $payload['subject'], $payload['message']);
    	return true;
    }
}

```

## Retry

If your handler fails, you can automatically retry by adding the `RetryTrait` to your handler class. Override the `maxRetry()` method to control the number of retry attempts (default is 25).

**Note:** Retry functionality requires a driver that supports delayed execution (the `$executeAt` message parameter). 

```php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class EchoHandler implements HandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool
    {
        throw new \Exception('this will always fail');
    }
    
    // optional - default is 25
    public function maxRetry(): int
    {
        return 10;
    }
}
```

## Priorities

You can configure multiple queues with different priority levels to ensure high-priority messages are processed first.

Example with Redis driver:
```php
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['a' => 'b'], Dispatcher::DEFAULT_PRIORITY - 10));
$emitter->emit(new Message('type1', ['c' => 'd'], Dispatcher::DEFAULT_PRIORITY + 10));
```

Key points about priorities:
- Use priority constants from the `Dispatcher` class or any numeric value
- Higher numbers indicate higher priority
- You can pass an array of queue names to `Dispatcher::handle()` to create workers that process specific queues

## Graceful Shutdown

Hermes workers can be gracefully stopped without losing messages.

When you provide an implementation of `Tomaj\Hermes\Shutdown\ShutdownInterface` to the `Dispatcher`, Hermes checks `ShutdownInterface::shouldShutdown()` after each message. If it returns `true`, the worker shuts down cleanly.

**Important:** Hermes handles shutdown, but automatic restart must be managed by your process controller (e.g., supervisord, systemd, or Docker).

Two shutdown implementations are available:

### SharedFileShutdown

Trigger shutdown by creating or touching a specific file:

```php
$shutdownFile = '/tmp/hermes_shutdown';
$shutdown = Tomaj\Hermes\Shutdown\SharedFileShutdown($shutdownFile);

// $log = ...
// $driver = ....
$dispatcher = new Dispatcher($driver, $log, $shutdown);

// ...

// shutdown can be triggered be calling `ShutdownInterface::shutdown()`
$shutdown->shutdown();
```

### RedisShutdown

Trigger shutdown by setting a Redis key:

```php
$redisClient = new Predis\Client();
$redisShutdownKey = 'hermes_shutdown'; // can be omitted; default value is `hermes_shutdown`
$shutdown = Tomaj\Hermes\Shutdown\RedisShutdown($redisClient, $redisShutdownKey);

// $log = ...
// $driver = ....
$dispatcher = new Dispatcher($driver, $log, $shutdown);

// ...

// shutdown can be triggered be calling `ShutdownInteface::shutdown()`
$shutdown->shutdown();
```

## Scaling Hermes

Hermes can easily scale to handle high message volumes. Simply run multiple worker instances—either on the same machine or distributed across multiple servers.

Requirements for scaling:
1. **Network-capable driver**: Your driver must support remote connections (Redis, RabbitMQ, and Amazon SQS all support this)
2. **At-most-once delivery**: Each message should be delivered to only one worker

Both Redis and RabbitMQ drivers satisfy these requirements and are designed for high-throughput scenarios.

## Extending Hermes

Hermes uses interface-based architecture, making it easy to extend. You can create custom drivers, use different loggers, or implement your own message serialization.

### Creating a Custom Driver

Each driver must implement `Tomaj\Hermes\Driver\DriverInterface` with two methods: `send()` and `wait()`.

Here's an example driver using [Gearman][]:

```PHP
namespace My\Custom\Driver;

use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Message;
use Closure;

class GearmanDriver implements DriverInterface
{
	private $client;

	private $worker;

	private $channel;

	private $serializer;

	public function __construct(GearmanClient $client, GearmanWorker $worker, $channel = 'hermes')
	{
		$this->client = $client;
		$this->worker = $worker;
		$this->channel = $channel;
		$this->serializer = $serialier;
	}

	public function send(Message $message)
	{
		$this->client->do($this->channel, $this->serializer->serialize($message));
	}

	public function wait(Closure $callback)
	{
		$worker->addFunction($this->channel, function ($gearmanMessage) use ($callback) {
			$message = $this->serializer->unserialize($gearmanMessage);
			$callback($message);
		});
		while ($this->worker->work());
	}
}
```

[Gearman]: http://gearman.org/

### Creating a Custom Serializer

To use custom serialization, create a class that implements `Tomaj\Hermes\SerializerInterface`. Add the `Tomaj\Hermes\Driver\SerializerAwareTrait` to your driver to enable the `setSerializer()` method.

Example using [jms/serializer][]:

```php
namespace My\Custom\Serializer;

use Tomaj\Hermes\SerializerInterface;
use Tomaj\Hermes\MessageInterface;

class JmsSerializer implements SerializerInterface
{
	public function serialize(MessageInterface $message)
	{
		$serializer = JMS\Serializer\SerializerBuilder::create()->build();
		return $serializer->serialize($message, 'json');
	}

	public function unserialize($string)
	{
		$serializer = JMS\Serializer\SerializerBuilder::create()->build();
		return $serializer->deserialize($message, 'json');
	}
}
```

[jms/serializer]: http://jmsyst.com/libs/serializer



### Scheduled Execution

Since version 2.0, you can schedule messages for future execution by passing a timestamp as the fourth parameter to the `Message` constructor. Currently supported by `RedisSetDriver` and `PredisSetDriver`.

## Upgrade Guide

### From v3 to v4

**Breaking Changes:**
- **Renamed Restart → Shutdown** to better reflect functionality. Hermes can gracefully stop its own process, but restarting must be handled by an external process manager.
  - `RestartInterface` → `ShutdownInterface`
  - All implementation classes and namespaces have been updated accordingly

## Changelog

See [CHANGELOG](CHANGELOG.md) for a detailed list of changes and version history.

## Testing

``` bash
$ composer test
```

### Code Coverage

To generate code coverage reports:

``` bash
# Generate coverage reports locally
$ composer coverage
# or use the helper script
$ ./coverage.sh
```

The coverage reports will be generated in:
- **HTML report**: `build/coverage/index.html` (open in browser to see line-by-line coverage)
- **Clover XML**: `build/logs/clover.xml` (for CI/CD integration)

**Online Coverage Reports**: Coverage reports are automatically published to GitHub Pages after each successful test run on the main branch.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security-related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
