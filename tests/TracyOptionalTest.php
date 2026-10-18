<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Driver\DummyDriver;
use Tomaj\Hermes\Test\Logger\TestLogger;
use Tracy\Debugger;

/**
 * tracy/tracy is only suggested: Dispatcher must work without it, and must not touch Tracy
 * unless the application enabled it. Each test runs in its own process, because Tracy's
 * state (loaded, enabled) is global.
 *
 * @covers \Tomaj\Hermes\Dispatcher
 */
class TracyOptionalTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testHandlerAndDriverErrorsAreLoggedWithoutTracyInstalled(): void
    {
        // what composer does when tracy/tracy is not installed: the class cannot be autoloaded
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->unregister();
            spl_autoload_register(static function (string $class) use ($loader): void {
                if (!str_starts_with($class, 'Tracy\\')) {
                    $loader->loadClass($class);
                }
            });
        }
        self::assertFalse(class_exists(Debugger::class));

        $logger = new TestLogger();
        $this->runFailingHandler($logger);
        $driverError = $this->runFailingDriver($logger);

        self::assertSame('driver blew up', $driverError->getMessage());
        $errors = array_values(array_filter($logger->getLogs(), static fn(array $log): bool => $log['level'] === 'error'));
        self::assertCount(2, $errors);
        self::assertStringContainsString('throws exception - handler failed', (string) $errors[0]['message']);
        self::assertStringContainsString('driver error: RuntimeException - driver blew up', (string) $errors[1]['message']);
    }

    #[RunInSeparateProcess]
    public function testTracyThatIsNotEnabledIsLeftAlone(): void
    {
        self::assertTrue(class_exists(Debugger::class));
        self::assertFalse(Debugger::isEnabled());

        // Debugger::log() without a log directory would throw, so reaching the end proves it was not called
        $logger = new TestLogger();
        $this->runFailingHandler($logger);
        $driverError = $this->runFailingDriver($logger);

        self::assertSame('driver blew up', $driverError->getMessage());
        self::assertCount(2, array_filter($logger->getLogs(), static fn(array $log): bool => $log['level'] === 'error'));
    }

    #[RunInSeparateProcess]
    public function testEnabledTracyReceivesHandlerAndDriverErrors(): void
    {
        $directory = sys_get_temp_dir() . '/hermes_tracy_' . bin2hex(random_bytes(4));
        mkdir($directory);
        Debugger::enable(Debugger::Production, $directory);

        try {
            $this->runFailingHandler(new TestLogger());
            $afterHandler = $this->files("{$directory}/exception--*.html");
            $this->runFailingDriver(new TestLogger());
            $afterDriver = $this->files("{$directory}/exception--*.html");

            self::assertCount(1, $afterHandler, 'the handler error is written to Tracy');
            self::assertCount(2, $afterDriver, 'the driver error is written to Tracy');
        } finally {
            foreach ($this->files("{$directory}/*") as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        $files = glob($pattern);

        return $files === false ? [] : $files;
    }

    private function runFailingHandler(TestLogger $logger): void
    {
        $dispatcher = new Dispatcher(new DummyDriver([new Message('job', [], 'a')]), $logger);
        $dispatcher->registerHandler('job', new class implements HandlerInterface {
            #[\Override]
            public function handle(MessageInterface $message): bool
            {
                throw new \RuntimeException('handler failed');
            }
        });
        $dispatcher->handle();
    }

    private function runFailingDriver(TestLogger $logger): \RuntimeException
    {
        $dispatcher = new Dispatcher(new class extends DummyDriver {
            #[\Override]
            public function wait(\Closure $callback, array $priorities = []): void
            {
                throw new \RuntimeException('driver blew up');
            }
        }, $logger);

        try {
            $dispatcher->handle();
        } catch (\RuntimeException $e) {
            return $e;
        }
        self::fail('the driver error must be rethrown');
    }
}
