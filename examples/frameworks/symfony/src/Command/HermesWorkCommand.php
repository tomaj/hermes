<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\DispatcherInterface;

#[AsCommand(name: 'hermes:work', description: 'Run the Hermes worker until it is told to stop')]
final class HermesWorkCommand extends Command
{
    public function __construct(private readonly DispatcherInterface $dispatcher)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('priority', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Handle only these priorities');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $priorities */
        $priorities = $input->getOption('priority');

        // Blocks until shutdown (hermes:restart) or the max items limit.
        $this->dispatcher->handle(array_map(intval(...), $priorities));

        return Command::SUCCESS;
    }
}
