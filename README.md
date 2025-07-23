# Hermes

**Background job processing PHP library**

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/hermes/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/hermes/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/hermes.svg)](https://packagist.org/packages/tomaj/hermes)
[![Phpstan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)

## What is Hermes?

If you need to process some task outside of HTTP request in your web app, you can utilize Hermes. Hermes provides message broker for sending messages from HTTP thread to offline processing jobs. Recommended use for sending emails, call other API or other time-consuming operations.

Another goal for Hermes is variability to use various message brokers like Redis, rabbit, database, and ability to easily create new drivers for other messaging solutions. And also the simple creation of workers to perform tasks on specified events.


## Installation

This library requires PHP 7.2 or later.

The recommended installation method is via Composer:

```bash
$ composer require tomaj/hermes
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Optional dependencies

Hermes is able to log activity with a logger that is compatible with `psr/log` interface. For more information take a look at [psr/log][].

The library works without logger, but maintainer recommends installing [monolog][] for logging.

[psr/log]: https://github.com/php-fig/log
[monolog]: https://github.com/Seldaek/monolog

## Supported drivers

Right now Hermes library is distributed with 3 drivers and one driver in a separate package:

 * [Redis][] driver (two different implementations [phpredis][] or [Predis][])
 * [Amazon SQS][] driverDispatcherRestartTest.php
 * [RabbitMQ][] driver
 * [ZeroMQ][] drivver (via [php-zmq][] extension) availabe as [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver) 

**Note:** You have to install all 3rd party libraries for initializing connections to these drivers. For example, you have to add `nrk/predis` to your *composer.json* and create a connection to your Redis instance.

[Amazon SQS]: https://aws.amazon.com/sqs/
[php-zmq]: https://zeromq.org/
[phpredis]: https://github.com/phpredis/phpredis
[Redis]: https://redis.io/
[RabbitMQ]: https://www.rabbitmq.com/
[Predis]: https://github.com/nrk/predis
[ZeroMQ]: https://zeromq.org/


## Concept - How Hermes works?

Hermes works as an emitter and Dispatcher for events from your PHP requests on the webserver to particular handler running on CLI. Basically like this:

```
--> HTTP request to /file.php -> emit(Message) -> Hermes Emitter
                                                             \  
                                                 Queue (Redis, rabbit etc.)
                                                             /
--> running PHP CLI file waiting for new Message-s from Queue
        when received a new message it calls registered handler to process it.
```

You have to implement these four steps in your application:

1. select driver that you would like to use and register it to Dispatcher and Emitter
2. emit events when you need to process something in the background
3. write a handler class that will process your message from 2.
4. create a PHP file that will run on your server "forever" and run Dispatcher there


## How to use

This simple example demonstrates using Redis driver and is an example of how to send email in the background.


### Emitting event

Emitting messages (anywhere in the application, easy and quick).

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

### Processing event

For processing an event, we need to create some PHP file that will be running in CLI. We can make this simple implementation and register this simple handler.


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

For running *handler.php* on your server you can use tools like [upstart][], [supervisord][], [monit][], [god][],  or any other alternative.

[upstart]: http://upstart.ubuntu.com/
[supervisord]: http://supervisord.org
[monit]: https://mmonit.com/monit/
[god]: http://godrb.com/

## Logging

Hermes can use any [psr/log][] logger. You can set logger for Dispatcher or Emitter and see what type of messages come to Dispatcher or Emitter and when a handler processed a message. If you add trait `Psr\Log\LoggerAwareTrait` (or implement `Psr\Log\LoggerAwareInterface`) to your handler, you can use logger also in your handler (Dispatcher and Emitter injects it automatically).

Basic example with [monolog][]:

```php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('hermes');
$log->pushHandler(new StreamHandler('hermes.log'));

// $driver = ....

$dispatcher = new Dispatcher($driver, $log);
```

and if you want to log also some information in handlers:

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

If you need to retry, you handle() method when they fail for some reason you can add `RetryTrait` to the handler.
If you want, you can override the `maxRetry()` method from this trait to specify how many times Hermes will try to run your handle().
**Warning:** if you want to use retry you have to use a driver that supports delayed execution (`$executeAt` message parameter) 

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

There is a possibility to declare multiple queues with different priority and ensure that messages in the high priority queue will be processed first.

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

Few details:
 - you can use priority constants from `Dispatcher` class, but you can also use any number
 - high number priority queue messages will be handled first
 - in `Dispatcher::handle()` method you can provide an array of queue names and create a worker that will handle only one or multiple selected queues

## Graceful shutdown

Hermes worker can be gracefully stopped.

If implementation of `Tomaj\Hermes\Shutdoown\ShutdownInteface` is provided when initiating `Dispatcher`, Hermes will check `ShutdwnInterface::shouldShutdown()` after each processed message. If it returns `true`, Hermes will shutdown _(notice is logged)_.

**WARNING:** relaunch is not provided by this library, and it should be handled by process controller you use to keep Hermes running _(e.g. launchd, daemontools, supervisord, etc.)_.

Currently, two methods are implemented.

### SharedFileShutdown

Shutdown initiated by touching predefined file.

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

Shutdown initiated by storing timestamp to Redis to predefined shutdown key.

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

If you have many messages that you need to process, you can scale your Hermes workers very quickly. You just run multiple instances of handlers - CLI files that will register handlers to Dispatcher and then run `$dispatcher->handle()`. You can also put your source codes to multiple machines and scale it out to as many nodes as you want. But it would help if you had a driver that supports these 2 things:

 1. driver needs to be able to work over the network
 2. one message must be delivered to only one worker

If you ensure this, Hermes will work correctly. Rabbit driver or Redis driver can handle this stuff, and these products are made for big loads, too.

## Extending Hermes

Hermes is written as separate classes that depend on each other via interfaces. You can easily change the implementation of classes. For example, you can create a new driver, use another logger. Or if you really want, you can create the format of your messages that will be sent to your driver serialized via your custom serializer.

### How to write your driver

Each driver has to implement `Tomaj\Hermes\Driver\DriverInterface` with 2 methods (**send** and **wait**). A simple driver that will use [Gearman][] as a driver

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

### How to write your own serializer

If you want o use your own serializer in your drivers, you have to create a new class that implements `Tomaj\Hermes\MessageSerializer`, and you need a driver that will support it. You can add the trait `Tomaj\Hermes\Driver\SerializerAwareTrait` to your driver that will add method `setSerializer` to your driver.

Simple serializer that will use library [jms/serializer][]:

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



### Scheduled execution

From version 2.0 you can add the 4th parameter to Message as a timestamp in the future. This message will be processed after this time. This functionality is supported in RedisSetDriver and PredisSetDriver right now.

### Upgrade

#### From v3 to v4

- Renamed Restart to Shutdown
  * Naming changed to reflect the functionality of Hermes. It can gracefully stop own process, but restart (relaunch) of Hermes has to be handled by external process/library. And therefore this is shutdown and not restart.
  * RestartInterface to ShutdownInterface
  * also all implementations changed namespace name and class name

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

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

**Online Coverage Reports**: Coverage reports are automatically published to GitHub Pages after each successful test run on the main branch. You can view them at: `https://[username].github.io/[repository-name]/`

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security-related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
