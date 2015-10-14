#!/usr/bin/env php
<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Tomaj\Hermes\Command\WorkerCommand;

$application = new Application();
$application->add(new WorkerCommand());
$application->run();
