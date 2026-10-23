<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use InvalidArgumentException;
use Throwable;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * Queue in a local directory, for a single server without a broker.
 *
 * Every priority queue is a subdirectory of $directory with four folders:
 *
 * - `tmp/`: send() writes the message here first;
 * - `new/`: then renames it here, so a worker never sees a half-written file;
 * - `processing/`: a worker claims a message by renaming it here, and deletes it after the callback;
 * - `failed/`: messages that cannot be unserialized.
 *
 * A file name starts with the time the message is due (executeAt, or the time it was created), so
 * sorting the names gives FIFO order and leaves delayed messages until they are due. rename() is atomic
 * on one local filesystem, so several workers can share a directory and each message is claimed once.
 * The claim adds the claim time to the name. A message whose worker died stays in `processing/`; after
 * $visibilityTimeout seconds it is moved back to `new/` and delivered again. Delivery is at least once.
 */
final class FileSystemDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    /** @var array<int, string> */
    private array $queues = [];

    /**
     * @param string $directory created when missing; every queue is a subdirectory
     * @param string $queue name of the queue for Dispatcher::DEFAULT_PRIORITY
     * @param int $refreshInterval seconds to sleep when no message is due; 0 polls without sleeping
     * @param int $visibilityTimeout seconds after which a claimed message whose worker did not finish
     *                               is delivered again; set it above your slowest handler, 0 never redelivers
     * @throws InvalidArgumentException when $refreshInterval or $visibilityTimeout is negative
     */
    public function __construct(
        private readonly string $directory,
        string $queue = 'hermes',
        private readonly int $refreshInterval = 1,
        private readonly int $visibilityTimeout = 300,
    ) {
        if ($refreshInterval < 0 || $visibilityTimeout < 0) {
            throw new InvalidArgumentException('refreshInterval and visibilityTimeout must not be negative');
        }
        $this->setupPriorityQueue($queue, Dispatcher::DEFAULT_PRIORITY);
        $this->serializer = new MessageSerializer();
    }

    /**
     * @throws UnknownPriorityException
     * @throws FileSystemDriverException
     * @throws SerializeException
     */
    #[\Override]
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $queue = $this->prepare($this->getQueue($priority));
        $name = FileSystemMessageName::create($message->getExecuteAt() ?? $message->getCreated(), bin2hex(random_bytes(8)));

        $temporary = "{$queue}/tmp/{$name}";
        if (@file_put_contents($temporary, $this->serializer->serialize($message)) === false) {
            throw new FileSystemDriverException("Cannot write message {$message->getId()} to {$temporary}");
        }
        if (!@rename($temporary, "{$queue}/new/{$name}")) {
            @unlink($temporary);
            throw new FileSystemDriverException("Cannot move message {$message->getId()} to {$queue}/new");
        }
        return true;
    }

    /**
     * @param string $name directory name of the queue: letters, digits, ".", "_" and "-", not starting with "."
     */
    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): void
    {
        if (preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/D', $name) !== 1) {
            throw new InvalidArgumentException("Invalid queue name \"{$name}\": use letters, digits, \".\", \"_\" and \"-\", not starting with \".\"");
        }
        $this->queues[$priority] = $name;
    }

    /**
     * @throws ShutdownException
     * @throws UnknownPriorityException
     * @throws SerializeException
     * @throws FileSystemDriverException
     */
    #[\Override]
    public function wait(Closure $callback, array $priorities = []): void
    {
        // Highest priority first, regardless of the order the queues were set up in
        $queues = $this->queues;
        krsort($queues, SORT_NUMERIC);
        if ($priorities !== []) {
            foreach ($priorities as $priority) {
                $this->getQueue($priority);
            }
            $queues = array_filter($queues, static fn(int $priority): bool => in_array($priority, $priorities, true), ARRAY_FILTER_USE_KEY);
        }
        $directories = [];
        foreach ($queues as $priority => $name) {
            $directories[$priority] = $this->prepare($name);
        }

        while (true) {
            $this->checkShutdown();
            if (!$this->shouldProcessNext()) {
                break;
            }

            $claimed = null;
            foreach ($directories as $priority => $directory) {
                $this->redeliverExpired($directory);
                $claimed = $this->claim($directory);
                if ($claimed !== null) {
                    $this->process($claimed, $directory, $priority, $callback);
                    break;
                }
            }

            if ($claimed === null) {
                sleep($this->refreshInterval);
            }
        }
    }

    /**
     * @throws SerializeException
     * @throws FileSystemDriverException
     */
    private function process(string $file, string $directory, int $priority, Closure $callback): void
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            throw new FileSystemDriverException("Cannot read {$file}");
        }
        try {
            $message = $this->serializer->unserialize($content);
        } catch (SerializeException $e) {
            @rename($file, "{$directory}/failed/" . FileSystemMessageName::unclaimed(basename($file)));
            throw $e;
        }

        try {
            $callback($message, $priority);
        } catch (ShutdownException $e) {
            // The dispatcher asks to stop after the message was handled.
            @unlink($file);
            throw $e;
        } catch (Throwable $e) {
            // Deliver it again, to this or another worker.
            @rename($file, "{$directory}/new/" . FileSystemMessageName::unclaimed(basename($file)));
            throw $e;
        }
        @unlink($file);
        $this->incrementProcessedItems();
    }

    /**
     * Claims the oldest due message by renaming it into processing/; returns its new path.
     */
    private function claim(string $directory): ?string
    {
        $now = microtime(true);
        foreach ($this->messageFiles("{$directory}/new") as $name) {
            if (!FileSystemMessageName::isDue($name, $now)) {
                // names are sorted by due time, so every later file is due later
                return null;
            }
            // The claim time is in the new name, so no other step can leave a claim without it.
            $target = "{$directory}/processing/" . FileSystemMessageName::claimed($name, time());
            if (@rename("{$directory}/new/{$name}", $target)) {
                return $target;
            }
            // another worker was faster
        }
        return null;
    }

    private function redeliverExpired(string $directory): void
    {
        if ($this->visibilityTimeout === 0) {
            return;
        }
        $now = time();
        foreach ($this->messageFiles("{$directory}/processing") as $name) {
            if (FileSystemMessageName::isExpired(FileSystemMessageName::claimTime($name), $now, $this->visibilityTimeout)) {
                @rename("{$directory}/processing/{$name}", "{$directory}/new/" . FileSystemMessageName::unclaimed($name));
            }
        }
    }

    /**
     * @return array<int, string> message file names, oldest due time first
     */
    private function messageFiles(string $directory): array
    {
        $names = @scandir($directory);
        if ($names === false) {
            return [];
        }
        return array_filter($names, FileSystemMessageName::isMessage(...));
    }

    /**
     * @throws FileSystemDriverException
     */
    private function prepare(string $queue): string
    {
        $path = rtrim($this->directory, '/\\') . "/{$queue}";
        foreach (['tmp', 'new', 'processing', 'failed'] as $folder) {
            $folderPath = "{$path}/{$folder}";
            // mkdir() fails when the folder exists, also when another worker just created it
            if (!@mkdir($folderPath, 0o775, true) && !is_dir($folderPath)) {
                throw new FileSystemDriverException("Cannot create directory {$folderPath}");
            }
        }
        return $path;
    }

    /**
     * @throws UnknownPriorityException
     */
    private function getQueue(int $priority): string
    {
        if (!isset($this->queues[$priority])) {
            throw new UnknownPriorityException("Unknown priority {$priority}");
        }
        return $this->queues[$priority];
    }
}
