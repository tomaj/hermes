<?php

declare(strict_types=1);

namespace App\Providers;

use App\Hermes\DriverFactory;
use App\Hermes\UserRegisteredHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\DispatcherInterface;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

final class HermesServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            DriverInterface::class,
            static fn(Application $app): DriverInterface => DriverFactory::create($app->make('config')->get('hermes')),
        );

        $this->app->singleton(
            ShutdownInterface::class,
            static fn(Application $app): ShutdownInterface => new SharedFileShutdown($app->make('config')->get('hermes.shutdown_file')),
        );

        // Producers (controllers, jobs, commands) depend on EmitterInterface.
        $this->app->singleton(
            EmitterInterface::class,
            static fn(Application $app): EmitterInterface => new Emitter(
                $app->make(DriverInterface::class),
                $app->make(LoggerInterface::class),
            ),
        );

        // Only the worker command resolves the dispatcher.
        $this->app->singleton(DispatcherInterface::class, static function (Application $app): DispatcherInterface {
            $dispatcher = new Dispatcher(
                $app->make(DriverInterface::class),
                $app->make(LoggerInterface::class),
                $app->make(ShutdownInterface::class),
            );
            $dispatcher->registerHandler('user.registered', $app->make(UserRegisteredHandler::class));

            return $dispatcher;
        });
    }
}
