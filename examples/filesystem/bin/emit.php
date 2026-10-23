<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// php bin/emit.php <report> [delay seconds] [high]
$report = $argv[1] ?? 'sales';
$delay = (int) ($argv[2] ?? 0);
$priority = ($argv[3] ?? '') === 'high' ? Hermes::PRIORITY_HIGH : Dispatcher::DEFAULT_PRIORITY;

$emitter = new Emitter(Hermes::driver());
$emitter->emit(new Message('report.export', ['report' => $report], executeAt: $delay > 0 ? microtime(true) + $delay : null), $priority);

echo "queued {$report}\n";
