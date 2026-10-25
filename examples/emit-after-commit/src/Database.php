<?php

declare(strict_types=1);

namespace App;

use Closure;
use PDO;
use Throwable;

/**
 * A small PDO wrapper with commit and rollback callbacks, like Nette Database or Laravel offer.
 */
final class Database
{
    public readonly PDO $pdo;

    /** @var list<Closure(): mixed> */
    private array $onCommit = [];

    /** @var list<Closure(): mixed> */
    private array $onRollback = [];

    public function __construct(string $file)
    {
        $this->pdo = new PDO('sqlite:' . $file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE)');
    }

    public function isInTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function registerOnCommit(callable $callback): void
    {
        $this->onCommit[] = $callback(...);
    }

    public function registerOnRollback(callable $callback): void
    {
        $this->onRollback[] = $callback(...);
    }

    /**
     * @template T
     * @param callable(PDO): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work($this->pdo);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            foreach ($this->onRollback as $callback) {
                $callback();
            }
            throw $e;
        }
        foreach ($this->onCommit as $callback) {
            $callback();
        }

        return $result;
    }
}
