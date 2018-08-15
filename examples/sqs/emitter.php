<?php
declare(strict_types=1);

use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
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
$emitter = new Emitter($driver);
$counter = 1;
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]));
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
