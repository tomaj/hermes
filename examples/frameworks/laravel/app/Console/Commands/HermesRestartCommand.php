<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

final class HermesRestartCommand extends Command
{
    protected $signature = 'hermes:restart';

    protected $description = 'Stop every running Hermes worker after its current message';

    public function handle(ShutdownInterface $shutdown): int
    {
        $shutdown->shutdown();
        $this->info('Workers will stop after their current message. Supervisor or systemd starts new ones.');

        return self::SUCCESS;
    }
}
