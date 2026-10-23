<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\FileSystemDriver;
use Tomaj\Hermes\Driver\FileSystemDriverException;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * @covers \Tomaj\Hermes\Driver\FileSystemDriver
 * @covers \Tomaj\Hermes\Driver\FileSystemMessageName
 */
final class FileSystemDriverTest extends TestCase
{
    private string $dir;

    #[\Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hermes-fs-' . bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $this->remove($this->dir);
        }
    }

    public function testSendWritesOneFileIntoNewAndLeavesTmpEmpty(): void
    {
        $driver = new FileSystemDriver($this->dir);
        self::assertTrue($driver->send(new Message('job', ['a' => 1], 'id-1', 1700000000.25)));

        self::assertSame([], $this->files('hermes/tmp'));
        $files = $this->files('hermes/new');
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('/^1700000000\.250000-[0-9a-f]{16}\.json$/', $files[0]);
        $message = new MessageSerializer()->unserialize((string) file_get_contents("{$this->dir}/hermes/new/{$files[0]}"));
        self::assertSame('id-1', $message->getId());
        self::assertSame(['a' => 1], $message->getPayload());
        foreach (['tmp', 'new', 'processing', 'failed'] as $folder) {
            self::assertDirectoryExists("{$this->dir}/hermes/{$folder}");
        }
    }

    public function testDelayedMessageIsNamedAfterItsDueTime(): void
    {
        $driver = new FileSystemDriver($this->dir . '/');
        $driver->send(new Message('job', null, 'late', 1700000000.0, 1800000000.5));
        $driver->send(new Message('job', null, 'past', 1700000000.0, -5.0));

        $files = $this->files('hermes/new');
        self::assertCount(2, $files);
        self::assertStringStartsWith('0000000000.000000-', $files[0]);
        self::assertStringStartsWith('1800000000.500000-', $files[1]);
    }

    public function testMessagesAreDeliveredOldestFirstAndDeleted(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'second', 1700000002.0));
        $driver->send(new Message('job', null, 'first', 1700000001.0));
        $driver->send(new Message('job', null, 'third', 1700000003.0));
        $driver->setMaxProcessItems(3);
        $driver->setShutdown(new PollBudgetShutdown());

        $received = [];
        $driver->wait(function (MessageInterface $message, int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        });

        self::assertSame([['first', 100], ['second', 100], ['third', 100]], $received);
        self::assertSame(3, $driver->processed());
        self::assertSame([], $this->files('q/new'));
        self::assertSame([], $this->files('q/processing'));
    }

    public function testHigherPriorityIsDeliveredFirstAndPrioritiesFilter(): void
    {
        $driver = new FileSystemDriver($this->dir, 'normal', 0);
        $driver->setupPriorityQueue('urgent', 200);
        $driver->setupPriorityQueue('low', 50);
        $driver->send(new Message('job', null, 'low-1', 1.0), 50);
        $driver->send(new Message('job', null, 'normal-1', 1.0));
        $driver->send(new Message('job', null, 'urgent-1', 2.0), 200);
        $driver->setMaxProcessItems(2);
        $driver->setShutdown(new PollBudgetShutdown());

        $received = [];
        $driver->wait(function (MessageInterface $message, int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        });
        self::assertSame([['urgent-1', 200], ['normal-1', 100]], $received);

        $only = new FileSystemDriver($this->dir, 'normal', 0);
        $only->setupPriorityQueue('urgent', 200);
        $only->setupPriorityQueue('low', 50);
        $only->send(new Message('job', null, 'normal-2', 1.0));
        $only->setMaxProcessItems(1);
        $only->setShutdown(new PollBudgetShutdown());
        $received = [];
        $only->wait(function (MessageInterface $message, int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        }, [50]);
        self::assertSame([['low-1', 50]], $received);
        self::assertCount(1, $this->files('normal/new'));
    }

    public function testFutureMessageWaitsUntilDue(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'later', null, microtime(true) + 3600));
        $driver->send(new Message('job', null, 'soon', null, microtime(true) + 0.3));
        $driver->setShutdown(new PollBudgetShutdown(1_000_000, 3.0));
        $driver->setMaxProcessItems(1);

        $received = [];
        $started = microtime(true);
        $driver->wait(function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });

        self::assertSame(['soon'], $received);
        self::assertGreaterThanOrEqual(0.25, microtime(true) - $started);
        self::assertCount(1, $this->files('q/new'));
    }

    public function testEmptyQueueSleepsForTheRefreshInterval(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 1);
        $driver->setShutdown(new PollBudgetShutdown(2));

        $started = microtime(true);
        try {
            $driver->wait(static function (): void {});
            self::fail('ShutdownException expected');
        } catch (ShutdownException) {
        }
        self::assertGreaterThanOrEqual(0.9, microtime(true) - $started);
    }

    public function testZeroRefreshIntervalDoesNotSleepOnAnEmptyQueue(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->setShutdown(new PollBudgetShutdown(50));

        $started = microtime(true);
        try {
            $driver->wait(static function (): void {});
            self::fail('ShutdownException expected');
        } catch (ShutdownException) {
        }
        self::assertLessThan(0.5, microtime(true) - $started);
    }

    public function testFailingCallbackPutsTheMessageBack(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'id-1', 1.0));
        $file = $this->files('q/new')[0];
        $driver->setMaxProcessItems(1);
        $driver->setShutdown(new PollBudgetShutdown());

        try {
            $driver->wait(static function (): never {
                throw new RuntimeException('handler crashed');
            });
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame('handler crashed', $e->getMessage());
        }

        self::assertSame([$file], $this->files('q/new'));
        self::assertSame([], $this->files('q/processing'));
        self::assertSame(0, $driver->processed());
    }

    public function testShutdownFromTheCallbackDeletesTheHandledMessage(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'id-1', 1.0));

        try {
            $driver->wait(static function (): never {
                throw new ShutdownException();
            });
            self::fail('ShutdownException expected');
        } catch (ShutdownException) {
        }

        self::assertSame([], $this->files('q/new'));
        self::assertSame([], $this->files('q/processing'));
    }

    public function testUnreadableMessageGoesToFailed(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'id-1', 1.0));
        $file = $this->files('q/new')[0];
        file_put_contents("{$this->dir}/q/new/{$file}", 'not json');

        try {
            $driver->wait(static function (): never {
                self::fail('the callback must not run');
            });
            self::fail('SerializeException expected');
        } catch (SerializeException) {
        }

        self::assertSame([$file], $this->files('q/failed'));
        self::assertSame([], $this->files('q/new'));
        self::assertSame([], $this->files('q/processing'));
    }

    public function testMessageFileThatCannotBeRead(): void
    {
        $this->skipAsRoot();
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'id-1', 1.0));
        $file = $this->files('q/new')[0];
        chmod("{$this->dir}/q/new/{$file}", 0o000);

        try {
            $driver->wait(static function (): never {
                self::fail('the callback must not run');
            });
            self::fail('FileSystemDriverException expected');
        } catch (FileSystemDriverException $e) {
            self::assertMatchesRegularExpression('#^Cannot read .+~' . preg_quote($file, '#') . '$#', $e->getMessage());
        } finally {
            chmod("{$this->dir}/q/processing/" . $this->files('q/processing')[0], 0o644);
        }
    }

    public function testClaimedMessagePastTheVisibilityTimeoutIsDeliveredAgain(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0, 60);
        $driver->send(new Message('job', null, 'stale', 1.0));
        $driver->send(new Message('job', null, 'fresh', 2.0));
        [$stale, $fresh] = $this->files('q/new');
        rename("{$this->dir}/q/new/{$stale}", "{$this->dir}/q/processing/" . (time() - 61) . "~{$stale}");
        $freshClaim = (time() - 59) . "~{$fresh}";
        rename("{$this->dir}/q/new/{$fresh}", "{$this->dir}/q/processing/{$freshClaim}");
        $driver->setMaxProcessItems(1);
        $driver->setShutdown(new PollBudgetShutdown());

        $received = [];
        $driver->wait(function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });

        self::assertSame(['stale'], $received);
        self::assertSame([$freshClaim], $this->files('q/processing'));
    }

    public function testZeroVisibilityTimeoutNeverRedelivers(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0, 0);
        $driver->send(new Message('job', null, 'stale', 1.0));
        $file = $this->files('q/new')[0];
        rename("{$this->dir}/q/new/{$file}", "{$this->dir}/q/processing/1000~{$file}");
        $driver->setShutdown(new PollBudgetShutdown(3));

        try {
            $driver->wait(static function (): never {
                self::fail('the callback must not run');
            });
            self::fail('ShutdownException expected');
        } catch (ShutdownException) {
        }
        self::assertSame(["1000~{$file}"], $this->files('q/processing'));
    }

    public function testClaimPutsTheClaimTimeInTheName(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0, 60);
        $driver->send(new Message('job', null, 'id-1', 1.0));
        $file = $this->files('q/new')[0];
        $before = time();

        $other = new FileSystemDriver($this->dir, 'q', 0, 60);
        $other->send(new Message('job', null, 'id-2', 2.0));
        $other->setMaxProcessItems(1);
        $other->setShutdown(new PollBudgetShutdown());
        $seen = [];
        $driver->setMaxProcessItems(1);
        $driver->setShutdown(new PollBudgetShutdown());
        $driver->wait(function (MessageInterface $message) use ($other, $file, $before, &$seen): void {
            // While this message is in processing/, another worker must not take it back.
            [$claimed] = $this->files('q/processing');
            self::assertStringEndsWith("~{$file}", $claimed);
            self::assertGreaterThanOrEqual($before, (int) $claimed);
            self::assertLessThanOrEqual(time(), (int) $claimed);
            $other->wait(function (MessageInterface $inner) use (&$seen): void {
                $seen[] = $inner->getId();
            });
            $seen[] = $message->getId();
        });

        self::assertSame(['id-2', 'id-1'], $seen);
    }

    public function testFilesWithoutTheExtensionAreIgnored(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->send(new Message('job', null, 'id-1', 5.0));
        file_put_contents("{$this->dir}/q/new/0000000000.000000-x.json.part", 'partial');
        $driver->setMaxProcessItems(1);
        $driver->setShutdown(new PollBudgetShutdown());

        $received = [];
        $driver->wait(function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });
        self::assertSame(['id-1'], $received);
    }

    public function testDispatcherRetryIsADelayedFileInTheDefaultQueue(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 0);
        $driver->setupPriorityQueue('q_high', Dispatcher::DEFAULT_PRIORITY + 10);
        $driver->send(new Message('job', ['a' => 1], 'id-1'), Dispatcher::DEFAULT_PRIORITY + 10);
        $driver->setMaxProcessItems(1);

        $dispatcher = new Dispatcher($driver);
        $dispatcher->registerHandler('job', new class implements HandlerInterface {
            use RetryTrait;

            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new RuntimeException('failed');
            }
        });
        $before = microtime(true);
        $dispatcher->handle();

        self::assertSame([], $this->files('q_high/new'));
        self::assertSame([], $this->files('q_high/processing'));
        $files = $this->files('q/new');
        self::assertCount(1, $files);
        self::assertGreaterThan($before, (float) $files[0]);
        $retry = new MessageSerializer()->unserialize((string) file_get_contents("{$this->dir}/q/new/{$files[0]}"));
        self::assertSame('id-1', $retry->getId());
        self::assertSame(1, $retry->getRetries());
        self::assertSame((float) $files[0], round((float) $retry->getExecuteAt(), 6));
    }

    public function testDefaults(): void
    {
        $defaults = [];
        foreach (new \ReflectionMethod(FileSystemDriver::class, '__construct')->getParameters() as $parameter) {
            $defaults[$parameter->getName()] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }
        self::assertSame(['directory' => null, 'queue' => 'hermes', 'refreshInterval' => 1, 'visibilityTimeout' => 300], $defaults);
    }

    public function testNegativeIntervalsAreRejected(): void
    {
        new FileSystemDriver($this->dir, 'q', 0, 0);
        foreach ([[-1, 0], [0, -1]] as [$refresh, $visibility]) {
            try {
                new FileSystemDriver($this->dir, 'q', $refresh, $visibility);
                self::fail('InvalidArgumentException expected');
            } catch (InvalidArgumentException $e) {
                self::assertSame('refreshInterval and visibilityTimeout must not be negative', $e->getMessage());
            }
        }
    }

    public function testFoldersAreCreatedWithMode0775LessTheUmask(): void
    {
        new FileSystemDriver($this->dir, 'q')->send(new Message('job'));
        $expected = 0o775 & ~umask();
        foreach (['tmp', 'new', 'processing', 'failed'] as $folder) {
            self::assertSame($expected, fileperms("{$this->dir}/q/{$folder}") & 0o777, $folder);
        }
    }

    public function testExistingFoldersAreReused(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q');
        $driver->send(new Message('job'));
        $driver->send(new Message('job'));
        self::assertCount(2, $this->files('q/new'));
    }

    public function testProcessedMessagesDoNotWaitForTheRefreshInterval(): void
    {
        $driver = new FileSystemDriver($this->dir, 'q', 2);
        $driver->send(new Message('job', null, 'a', 1.0));
        $driver->send(new Message('job', null, 'b', 2.0));
        $driver->setMaxProcessItems(2);
        $driver->setShutdown(new PollBudgetShutdown());

        $started = microtime(true);
        $driver->wait(static function (): void {});
        self::assertLessThan(1.5, microtime(true) - $started);
    }

    public function testUnknownPriority(): void
    {
        $driver = new FileSystemDriver($this->dir);
        try {
            $driver->send(new Message('job'), 7);
            self::fail('UnknownPriorityException expected');
        } catch (UnknownPriorityException $e) {
            self::assertSame('Unknown priority 7', $e->getMessage());
        }

        $driver->setShutdown(new PollBudgetShutdown());
        $this->expectException(UnknownPriorityException::class);
        $driver->wait(static function (): void {}, [7]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'parent' => ['..'];
        yield 'hidden' => ['.queue'];
        yield 'slash' => ['a/b'];
        yield 'backslash' => ['a\\b'];
        yield 'space' => ['a b'];
        yield 'newline at the end' => ["queue\n"];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidQueueNameIsRejected(string $name): void
    {
        try {
            new FileSystemDriver($this->dir, $name);
            self::fail('InvalidArgumentException expected');
        } catch (InvalidArgumentException $e) {
            self::assertStringStartsWith("Invalid queue name \"{$name}\"", $e->getMessage());
        }
    }

    public function testValidQueueNames(): void
    {
        $driver = new FileSystemDriver($this->dir, 'Emails_v2.high-1');
        $driver->setupPriorityQueue('_low', 1);
        $driver->setupPriorityQueue('9', 2);
        $driver->send(new Message('job'), 1);
        $driver->send(new Message('job'), 2);
        $driver->send(new Message('job'));
        self::assertCount(1, $this->files('_low/new'));
        self::assertCount(1, $this->files('9/new'));
        self::assertCount(1, $this->files('Emails_v2.high-1/new'));
    }

    public function testDirectoryThatCannotBeCreated(): void
    {
        mkdir($this->dir);
        file_put_contents("{$this->dir}/q", 'a file where the queue directory should be');
        $driver = new FileSystemDriver($this->dir . '/', 'q');

        try {
            $driver->send(new Message('job'));
            self::fail('FileSystemDriverException expected');
        } catch (FileSystemDriverException $e) {
            self::assertSame("Cannot create directory {$this->dir}/q/tmp", $e->getMessage());
        }
    }

    public function testMessageThatCannotBeWritten(): void
    {
        $this->skipAsRoot();
        $driver = new FileSystemDriver($this->dir, 'q');
        $driver->send(new Message('job'));
        chmod("{$this->dir}/q/tmp", 0o555);

        $this->expectException(FileSystemDriverException::class);
        $this->expectExceptionMessageMatches('/^Cannot write message id-9 to .+\/q\/tmp\/[0-9.]+-[0-9a-f]{16}\.json$/');
        $driver->send(new Message('job', null, 'id-9'));
    }

    public function testMessageThatCannotBeMovedIsRemovedFromTmp(): void
    {
        $this->skipAsRoot();
        $driver = new FileSystemDriver($this->dir, 'q');
        $driver->send(new Message('job'));
        chmod("{$this->dir}/q/new", 0o555);

        try {
            $driver->send(new Message('job', null, 'id-9'));
            self::fail('FileSystemDriverException expected');
        } catch (FileSystemDriverException $e) {
            self::assertSame("Cannot move message id-9 to {$this->dir}/q/new", $e->getMessage());
        }
        self::assertSame([], $this->files('q/tmp'));
    }

    private function skipAsRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root ignores directory permissions');
        }
    }

    /**
     * @return list<string>
     */
    private function files(string $folder): array
    {
        $names = scandir("{$this->dir}/{$folder}");
        self::assertIsArray($names);
        return array_values(array_diff($names, ['.', '..']));
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        chmod($path, 0o755);
        foreach (array_diff((array) scandir($path), ['.', '..']) as $name) {
            $this->remove("{$path}/{$name}");
        }
        rmdir($path);
    }
}
