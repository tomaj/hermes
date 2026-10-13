<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if ($email === false || $email === null) {
        http_response_code(422);
        echo 'Enter a valid email address.';
        return;
    }

    // Save the user here. Then hand the slow part to the worker.
    Hermes::emitter()->emit(new Message('user.registered', [
        'email' => $email,
        'name' => trim((string) ($_POST['name'] ?? '')),
    ]));

    echo "Thanks! A welcome email is on its way to {$email}.";
    return;
}
?>
<form method="post">
    <input name="name" placeholder="Name">
    <input name="email" type="email" placeholder="you@example.com" required>
    <button>Register</button>
</form>
