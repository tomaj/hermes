<?php

declare(strict_types=1);

namespace App;

use LimitIterator;
use PDO;
use SplFileObject;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class ImportChunkHandler implements HandlerInterface
{
    use RetryTrait;

    public function __construct(private PDO $db) {}

    public function handle(MessageInterface $message): bool
    {
        ['path' => $path, 'offset' => $offset, 'limit' => $limit] = $message->getPayload();

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        // Line 0 is the header, so data row N is line N + 1.
        $insert = $this->db->prepare(
            'INSERT INTO customers (email, name) VALUES (:email, :name)
             ON CONFLICT (email) DO UPDATE SET name = excluded.name',
        );

        // One transaction per chunk: a failed chunk is retried as a whole, and the upsert makes that safe.
        $this->db->beginTransaction();
        try {
            foreach (new LimitIterator($file, $offset + 1, $limit) as $row) {
                [$email, $name] = $row + [null, null];
                if ($email === null || $email === '') {
                    continue;
                }
                $insert->execute(['email' => strtolower(trim($email)), 'name' => trim((string) $name)]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }
}
