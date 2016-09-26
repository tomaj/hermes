<?php

use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\EchoHandler;
use Aws\Sqs\SqsClient;

require_once __DIR__.'/../../vendor/autoload.php';

$client = new Aws\Sqs\SqsClient([
    'version' => 'latest',
    'region'  => '*region*',
    'credentials' => [
        'key' => '*key*',
        'secret' => '*secret*',
    ]
]);

$driver = new AmazonSqsDriver($client, '*queueName*');
$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
