# Hermes

[![Join the chat at https://gitter.im/tomaj/hermes](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/tomaj/hermes?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

**Background job processing PHP library**

[![Build Status](https://travis-ci.org/tomaj/hermes.svg)](https://travis-ci.org/tomaj/hermes)
[![Dependency Status](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc/badge.svg?style=flat)](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/hermes/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/hermes/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tomaj/hermes/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tomaj/hermes/?branch=master)
[![Latest Stable Version](https://img.shields.io/packagist/v/tomaj/hermes.svg)](https://packagist.org/packages/tomaj/hermes)

## What is Hermes?

If you need to process some task outside of http request in your web app, you can utilize Hermes. Hermes provides message broker for sending messages from http thread to offline processing jobs. Recommended use for sending emails, call other API or other time consuming operations.

Other goal for Hermes is variability to use various message brokers like redis, rabbit, database and ability to easy create new drivers for other messaging solutions. And also simple creation of workers to perform tasks on specified events.


## Installation

This library requires PHP 5.4 or later. It works also on HHVM and PHP 7.0.

Recommended installation method is via Composer:

```bash
$ composer require tomaj/hermes
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Optional dependencies

Hermes is able to log activity with logger that is compatible with `psr/log` interface. For more information take a look at [psr/log][].

Library works without logger but maintener recommends installing [monolog][] for logging.

[psr/log]: https://github.com/php-fig/log
[monolog]: https://github.com/Seldaek/monolog


## Framework integrations

 * Laravel provider (not yet implemented)
 * Nette provider (not yet implemented)
 * Simple CLI example (not yet implemented)

## Supported drivers

Right now Hermes library is distributed with 2 drivers:

 * [Redis][] driver ([phpredis][] or [Predis][])
 * [RabbitMQ][] driver
 * [ZeroMQ][] drivver (via [php-zmq][] extension)

Note: You have to install all 3rd party libraries for initializing connections to this drivers. For example you have to add `nrk/predis` to your *composer.json* and create connection to your redis instance.

[Redis]: http://redis.io/
[RabbitMQ]: https://www.rabbitmq.com/
[ZeroMQ]: http://zeromq.org/
[phpredis]: https://github.com/phpredis/phpredis
[Predis]: https://github.com/nrk/predis
[php-zmq]: http://zeromq.org/bindings:php


## Concept - How hermes works?

Hermes works as a dispatcher for events from your php requests on webserver to particular handler running on cli. Basicaly like this:

```
--> http request to /file.php -> emit(Message) -> Hermes Dispatcher
                                                             \  
                                                 Queue (redis, rabbit etc...)
                                                             /
--> running php cli file waiting for new Message-s from Queue
        when received new message it calls registered handler to process it.
```

You have to implement these four steps in your application:

1. select driver that you would like to use and register it to Dispatcher
2. emit events when you need to process something in the background
3. write handler class that will process your message from 2.
4. create php file that will run on your server "forever" and run Dispatcher there


## How to use

This simple example demonstrates using Redis driver and is an example how to send email in the background.


### Emitting event

Emmitting messages (anywhere in application, easy and quick).

```php
use Redis;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);

$message = new Message('send-email', [
	'to' => 'test@test.com',
	'subject' => 'Testing hermes email',
	'message' => 'Hello from hermes!'
]);

$dispatcher->emit($message);
```

### Processing event

For processing an event we need to create some php file that will be running in CLI. We can create this simple implementation and register this simple handler.


```php
# file handler.php
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;

class SendEmailHandler implements HandlerInterface
{
    // here you will receive message that was emmited from web aplication
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

Hermes can use any [psr/log][] logger. You can set logger for Dispatcher and see what type of messages come to Dispatcher and when a handler processed a message. If you add trait `Psr\Log\LoggerAwareTrait` (or implement `Psr\Log\LoggerAwareInterface`) to your handler, you can use logger also in your handler (Dispatcher injects it automatically).

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

# Scaling Hermes

If you have a lot of messages that you need to process, you can scale your Hermes workers very easily. You just run multiple instances of handlers - cli files that will register handlers to dispatcher and then run `$dispatcher->handle()`. You can also put your source codes to multiple machines and scale it out to as many nodes as you want. But you need a driver that supports these 2 things:

 1. driver needs to be able to work over network
 2. one message must be delivered to only one worker

If you ensure this, Hermes will work perfectly. Rabbit driver or Redis driver can handle this stuff and these products are made for big loads, too.

# Extending Hermes

Hermes is written as separated classes that depend on each other via interfaces. You can easily change implementation of classes. For example you can create new driver, use other logger. Or if you really want, you can create your own messages format that will be send to your driver serialized via your custom serializer.

### How to write your own driver

Each driver has to implement `Tomaj\Hermes\Driver\DriverInterface` with 2 methods (**send** and **wait**). Simple driver that will use [Gearman][] as a driver

```php
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

If you want o use your own serializer in your drivers, you have to create a new class that implements `Tomaj\Hermes\MessageSerializer` and you need a driver that will support it. You can add trait `Tomaj\Hermes\Driver\SerializerAwareTrait` to your driver that will add method `setSerializer` to your driver.

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


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email tomasmajer@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
