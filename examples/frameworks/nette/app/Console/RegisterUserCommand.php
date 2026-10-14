<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Message;

#[AsCommand(name: 'app:register-user', description: 'Emit user.registered for one email address')]
final class RegisterUserCommand extends Command
{
    public function __construct(private readonly EmitterInterface $emitter)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $this->emitter->emit(new Message('user.registered', ['email' => $email]));
        $output->writeln("Queued the welcome email for {$email}.");

        return Command::SUCCESS;
    }
}
