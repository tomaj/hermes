<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

#[AsCommand(name: 'hermes:restart', description: 'Stop every running Hermes worker after its current message')]
final class HermesRestartCommand extends Command
{
    public function __construct(private readonly ShutdownInterface $shutdown)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->shutdown->shutdown();
        $output->writeln('Workers will stop after their current message. Supervisor or systemd starts new ones.');

        return Command::SUCCESS;
    }
}
