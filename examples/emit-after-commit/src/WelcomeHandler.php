<?php

declare(strict_types=1);

namespace App;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

final readonly class WelcomeHandler implements HandlerInterface
{
    public function __construct(private Database $database, private string $outputFile) {}

    public function handle(MessageInterface $message): bool
    {
        $id = $message->getPayload()['user_id'] ?? null;
        $select = $this->database->pdo->prepare('SELECT email FROM users WHERE id = ?');
        $select->execute([$id]);
        $email = $select->fetchColumn();

        // without BufferedEmitter the worker could get here before the commit and find nothing
        $line = $email === false ? "missing user {$id}\n" : "welcome {$email}\n";
        file_put_contents($this->outputFile, $line, FILE_APPEND | LOCK_EX);

        return $email !== false;
    }
}
