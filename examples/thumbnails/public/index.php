<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo "Upload a file as the image field.\n";
    return;
}

$name = bin2hex(random_bytes(8)) . '.jpg';
$path = __DIR__ . '/../storage/originals/' . $name;
@mkdir(dirname($path), 0o775, true);
move_uploaded_file($_FILES['image']['tmp_name'], $path);

$emitter = new Emitter(Hermes::driver());
$emitter->emit(new Message('image.thumbnails', [
    'path' => realpath($path),
    'sizes' => [160, 640, 1280],
]));

http_response_code(202);
echo "Uploaded {$name}, thumbnails are on the way.\n";
