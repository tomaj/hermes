<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

/**
 * Graceful stop on SIGTERM and SIGINT while Dispatcher::handle() runs (needs ext-pcntl).
 *
 * The first signal only sets a flag. Signals are dispatched synchronously at the safe points
 * (after a message, and at each driver shutdown check), so the dispatcher stops after the current
 * message and an idle driver stops at its next check. Further signals are ignored until then;
 * SIGKILL still ends the process at once.
 *
 * Signals belong to the process, so the flag is static. The object returned by install()
 * restores the previous handlers when it is destroyed.
 *
 * @internal
 */
final class SignalStop
{
    private static ?int $signal = null;

    private static bool $listening = false;

    /** @var array<int, callable|int> */
    private array $previous = [];

    private bool $previousAsync = false;

    private function __construct() {}

    public function __destruct()
    {
        foreach ($this->previous as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }
        if ($this->previous !== []) {
            pcntl_async_signals($this->previousAsync);
            self::$listening = false;
        }
    }

    public static function install(): self
    {
        self::$signal = null;
        $guard = new self();
        if (!function_exists('pcntl_signal')) {
            return $guard;
        }

        // Synchronous delivery: received() dispatches at the safe points. With async delivery a
        // SIGTERM that arrives inside php-amqplib's socket wait was sometimes never handled.
        $guard->previousAsync = pcntl_async_signals(false);
        foreach ([SIGTERM, SIGINT] as $signal) {
            $guard->previous[$signal] = pcntl_signal_get_handler($signal);
            pcntl_signal($signal, static function (int $received): void {
                self::$signal ??= $received;
            });
        }
        self::$listening = true;

        return $guard;
    }

    public static function received(): ?int
    {
        if (self::$listening) {
            pcntl_signal_dispatch();
        }

        return self::$signal;
    }

    public static function describe(int $signal): string
    {
        return match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            default => "signal {$signal}",
        };
    }
}
