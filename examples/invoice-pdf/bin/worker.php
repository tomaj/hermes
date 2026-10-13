<?php

declare(strict_types=1);

use App\Hermes;
use App\InvoicePdfHandler;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

$directory = __DIR__ . '/../storage/invoices';
if (!is_dir($directory)) {
    mkdir($directory, 0o775, true);
}

$redis = Hermes::redis();
$driver = Hermes::driver($redis);
$driver->setShutdown(new PredisShutdown($redis));
$driver->setMaxProcessItems(200); // dompdf is memory hungry, start a fresh process now and then

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('invoice.generate', new InvoicePdfHandler($directory));
$dispatcher->handle();
