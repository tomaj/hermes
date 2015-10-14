# hermes

[![Build Status](https://travis-ci.org/tomaj/hermes.svg)](https://travis-ci.org/tomaj/hermes)

## todo

* create global bin command
* create rabbit and redis drivers
* create nette library
* create nette-database driver
* add DocBloks
* add Changelog
* add logging
* add extensive readme
* create laravel library
* ability to re-send message with retry-count
* enable code coverage
* delayed execution (ability to process message after interval)
* change time() to microtime()
* add recomended packages to composer


## Usage

Installation: ```composer require tomaj/hermes```

There are two part for use - emitting messages and handling.

Emmiting messages (anywhere in aplication, easy and quick). You can see *bin/send.php* file

```php
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;

$driver = new RedisSetDriver();
$dispatcher = new Dispatcher($driver);

$message = new Message('eventtype', ['data' => 'anything']);

$dispatcher->emit($message);

```


For handling and processing message you will need handler and register it to dispatcher:

```php
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\

class MyHandler implements HandlerInterface
{
	public function handle(MessageInterface $message)
    {
    	// code to process message
    }
}


$driver = new RedisSetDriver();
$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('eventtype', new MyHandler());

$dispatcher->handle();
```