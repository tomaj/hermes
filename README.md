# hermes

[![Build Status](https://travis-ci.org/tomaj/hermes.svg)](https://travis-ci.org/tomaj/hermes)
[![Dependency Status](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc/badge.svg?style=flat)](https://www.versioneye.com/user/projects/561e165436d0ab00210000dc)
[![Code Climate](https://codeclimate.com/github/tomaj/hermes/badges/gpa.svg)](https://codeclimate.com/github/tomaj/hermes)
[![Test Coverage](https://codeclimate.com/github/tomaj/hermes/badges/coverage.svg)](https://codeclimate.com/github/tomaj/hermes/coverage)
[![Latest Stable Version](https://img.shields.io/packagist/v/monolog/monolog.svg)](https://packagist.org/packages/monolog/monolog)

## Usage

Installation: ```composer require tomaj/hermes```

There are two part for use - emitting messages and handling.

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
