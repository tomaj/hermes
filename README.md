# hermes

[![Build Status](https://travis-ci.org/tomaj/hermes.svg)](https://travis-ci.org/tomaj/hermes)
[![Dependency Status](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc/badge.svg?style=flat)](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tomaj/hermes/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tomaj/hermes/?branch=master)
[![Code Climate](https://codeclimate.com/github/tomaj/hermes/badges/gpa.svg)](https://codeclimate.com/github/tomaj/hermes)
[![Test Coverage](https://codeclimate.com/github/tomaj/hermes/badges/coverage.svg)](https://codeclimate.com/github/tomaj/hermes/coverage)
[![Latest Stable Version](https://img.shields.io/packagist/v/monolog/monolog.svg)](https://packagist.org/packages/monolog/monolog)

## What is Hermes?

If you need process some task outside of http request in your web app you can utilize hermes. Hermes provides message broker for sending messages from http thread to offline processing jobs. Recommended use for sending emails, call other API or other time consuming operations.

Other goal for hermes is variability to use various message brokers like redis, rabbit, database and ability to easy create new drivers for other messaging solutions. And also simple creation of workers to perform tasks on specified events.


## Instalation

This library requires PHP 5.4 or later. It works also an HHVM and PHP 7.0.

Recommended instlation method is via Composer:

```bash
$ composer require tomaj/hermes
```

Library is compliant with [PSR-1][], [PSR-2][], [PSR-3][] and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-3]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


## Optional dependencies

Hermes is able to log activity with logger that is compatible with `psr/log` interface. For more information take a look at [psr/log][]

Library works without logger but maintener recommends installing [monolog][] for logging.

[psr/log]: https://github.com/php-fig/log
[monolog]: https://github.com/Seldaek/monolog


## Framework integrations

 * Laravel provider (not yet implemented)
 * Nette provider (not yet implemented)
 * Simple CLI example (not yet implemneted)

## Supported drivers

Righ now Hermes library is distributed with 2 drivers:

 * Redis driver ([phpredis][] and [Predis][])
 * RabbitMQ driver 
 * PDO driver (not yet)

Note: You have to install all 3th party libraries for initializing connections to this drivers. For example you have to add `nrk/predis` to your composer.json and create connection to your redis instance.

[phpredis]: https://github.com/phpredis/phpredis
[Predis]: https://github.com/nrk/predis


## Concept - How hermes works?

Hermes works as dispatcher for events from your php requests on webserver to particular handler running on cli. Basicaly like this:

```
--> request to /file.php -> emit(Message) -> Hermes Dispatcher
                                                             \  
                                                 Queue (redis, rabbit etc...)
                                                             /
--> running php cli file like worker waiting for new Message-s from Queue
        when received message call you registered handler to process it.
```

You have implement in you application:

1. select driver that you would like to use and put it on Dispatcher
2. emit events when you need to process something in background
3. write handler class that will process you message from 1.
4. create php file that will run on you server "forever" and register there dispatcher


# How to use

Emmiting messages (anywhere in aplication, easy and quick). You can see *bin/send.php* file

```php
use Redis;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);

$message = new Message('eventtype', ['data' => 'anything']);

$dispatcher->emit($message);

```


For handling and processing message you will need handler and register it to dispatcher:

```php
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;

class MyHandler implements HandlerInterface
{
	public function handle(MessageInterface $message)
    {
    	// code to process message
    }
}


$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('eventtype', new MyHandler());

$dispatcher->handle();
```

# Extend hermes

Hermes is written as separated classes that depends on each other via interfaces. You can easily change implementation of classes. For example you can create new driver, use other logger. Or if you really want you can create your own messages format that will be send to your driver serialized via your custom serializer.

# How to write your own driver

TODO

# How to write your own serializer

TODO

