<?php

use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\EchoHandler;
use Aws\Sqs\SqsClient;

require_once __DIR__.'/../../vendor/autoload.php';

$client = SqsClient::factory([
	'version' => 'latest',
    'region'  => 'eu-west-1',
    'key' => '*key*',
    'secret' => '*secret*',
]);

$driver = new AmazonSqsDriver($client, '*queueName*');
$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
