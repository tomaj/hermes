<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

final class ThumbnailHandler implements HandlerInterface
{
    public function __construct(private string $directory) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        ['path' => $path, 'sizes' => $sizes] = $message->getPayload();

        $source = @imagecreatefromstring((string) @file_get_contents($path));
        if ($source === false) {
            // A broken upload stays broken. Return false instead of throwing, so nothing is retried.
            return false;
        }

        $base = pathinfo($path, PATHINFO_FILENAME);
        foreach ($sizes as $width) {
            $thumb = imagescale($source, (int) $width);
            if ($thumb === false || !imagejpeg($thumb, "{$this->directory}/{$base}-{$width}.jpg", 82)) {
                throw new RuntimeException("Cannot write the {$width}px thumbnail of {$path}");
            }
        }

        return true;
    }
}
