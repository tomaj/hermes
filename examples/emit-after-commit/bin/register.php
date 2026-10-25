<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// php bin/register.php <email> [fail]
$email = $argv[1] ?? 'alice@example.com';
$fail = ($argv[2] ?? '') === 'fail';

$database = Hermes::database();
$emitter = Hermes::emitter($database);

try {
    $database->transaction(static function (PDO $pdo) use ($emitter, $email, $fail): void {
        $pdo->prepare('INSERT INTO users (email) VALUES (?)')->execute([$email]);
        $emitter->emit(new Message('user.registered', ['user_id' => (int) $pdo->lastInsertId()]));

        echo "in transaction: {$emitter->count()} buffered, " . count(glob(Hermes::directory() . '/hermes/users/new/*') ?: []) . " queued\n";
        if ($fail) {
            throw new RuntimeException('payment failed');
        }
    });
    echo "registered {$email}\n";
} catch (RuntimeException $e) {
    echo "rolled back {$email}: {$e->getMessage()}, {$emitter->count()} buffered\n";
}
