<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tomaj\Hermes\DispatcherInterface;

final class HermesWorkCommand extends Command
{
    protected $signature = 'hermes:work {--priority=* : Handle only these priorities}';

    protected $description = 'Run the Hermes worker until it is told to stop';

    public function handle(DispatcherInterface $dispatcher): int
    {
        $priorities = array_map(intval(...), (array) $this->option('priority'));

        // Blocks until shutdown (hermes:restart) or the max items limit.
        $dispatcher->handle($priorities);

        return self::SUCCESS;
    }
}
