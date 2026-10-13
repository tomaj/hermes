<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

const CHUNK = 500;

if (($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo "Upload a file as the csv field.\n";
    return;
}

// Keep the upload somewhere the worker can read it.
$path = __DIR__ . '/../storage/' . bin2hex(random_bytes(8)) . '.csv';
if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0o775, true);
}
move_uploaded_file($_FILES['csv']['tmp_name'], $path);

// Count data rows (the first line is the header), then queue one message per chunk.
$rows = -1;
foreach (new SplFileObject($path) as $line) {
    if ($line !== '' && $line !== false) {
        $rows++;
    }
}

$emitter = Hermes::emitter();
for ($offset = 0; $offset < $rows; $offset += CHUNK) {
    $emitter->emit(new Message('csv.import_chunk', ['path' => $path, 'offset' => $offset, 'limit' => CHUNK]));
}

http_response_code(202);
printf("%d rows queued in %d chunks.\n", $rows, (int) ceil($rows / CHUNK));
