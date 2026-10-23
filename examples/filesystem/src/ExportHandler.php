<?php

declare(strict_types=1);

namespace App;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

final readonly class ExportHandler implements HandlerInterface
{
    public function __construct(private string $outputFile) {}

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload() ?? [];
        $line = sprintf("%s export %s\n", date('H:i:s'), $payload['report'] ?? '?');
        file_put_contents($this->outputFile, $line, FILE_APPEND | LOCK_EX);

        return true;
    }
}
