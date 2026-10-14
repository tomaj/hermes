<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Message;

final class RegisterUserCommand extends Command
{
    protected $signature = 'app:register-user {email}';

    protected $description = 'Emit user.registered for one email address';

    public function handle(EmitterInterface $emitter): int
    {
        $email = (string) $this->argument('email');
        $emitter->emit(new Message('user.registered', ['email' => $email]));
        $this->info("Queued the welcome email for {$email}.");

        return self::SUCCESS;
    }
}
