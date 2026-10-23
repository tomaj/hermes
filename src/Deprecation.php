<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

use ReflectionMethod;
use ReflectionNamedType;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

/**
 * Deprecation notices for code that behaves differently on Hermes 6.0.
 *
 * Notices are E_USER_DEPRECATED and silenced with @, as Symfony does: they reach error handlers that
 * collect deprecations (Symfony's debug toolbar, PHPUnit's bridge, Monolog's ErrorHandler) without
 * turning into warnings in production.
 *
 * @internal
 */
final class Deprecation
{
    public static function trigger(string $message): void
    {
        @trigger_error("Since tomaj/hermes 5.1: {$message}", E_USER_DEPRECATED);
    }

    /**
     * Reports a subclass of a class that is final in 6.0.
     */
    public static function subclass(object $object, string $class): void
    {
        if (get_class($object) !== $class) {
            $actual = self::name($object);
            self::trigger("{$actual} extends {$class}, which is final in 6.0. Wrap it or implement its interface instead.");
        }
    }

    /**
     * Reports a shutdown whose methods take DateTime: 6.0 passes DateTimeInterface.
     */
    public static function shutdown(ShutdownInterface $shutdown): void
    {
        foreach (['shouldShutdown', 'shutdown'] as $method) {
            $parameters = (new ReflectionMethod($shutdown, $method))->getParameters();
            $type = isset($parameters[0]) ? $parameters[0]->getType() : null;
            if ($type instanceof ReflectionNamedType && $type->getName() === 'DateTime') {
                $class = self::name($shutdown);
                self::trigger("{$class}::{$method}() takes DateTime; 6.0 passes DateTimeInterface. Widen the parameter type to DateTimeInterface, which 5.x accepts as well.");
            }
        }
    }

    /**
     * Reports a priority order that changes in 6.0, which polls the highest priority first.
     *
     * @param int[] $order priorities in the order this driver polls them
     */
    public static function priorityOrder(string $driver, array $order): void
    {
        $highestFirst = $order;
        rsort($highestFirst);
        if ($order !== $highestFirst) {
            self::trigger("{$driver} polls the priorities in reverse registration order (" . implode(', ', $order) . '); 6.0 polls the highest number first (' . implode(', ', $highestFirst) . ').');
        }
    }

    /**
     * Reports a retry that 6.0 handles differently.
     */
    public static function retry(HandlerInterface $handler): void
    {
        $class = self::name($handler);
        if (!self::usesRetryTrait($handler)) {
            self::trigger("{$class} is retried because it has canRetry() and maxRetry(); 6.0 retries only handlers that implement RetryableHandlerInterface or use RetryTrait.");
            return;
        }
        if (self::canRetryIsFromTheTrait($handler)) {
            return;
        }
        self::trigger("6.0 calls {$class}::canRetry(\$message, \$exception) and skips the retry when it returns false; 5.x does not call it. Implement RetryableHandlerInterface to get the 6.0 behaviour now.");
    }

    /**
     * The class name without the NUL byte and file of an anonymous class, which would cut the message.
     */
    public static function name(object $object): string
    {
        return explode("\0", get_class($object))[0];
    }

    private static function canRetryIsFromTheTrait(HandlerInterface $handler): bool
    {
        // a method copied from a trait reports the trait's file
        return (new ReflectionMethod($handler, 'canRetry'))->getFileName() === (new ReflectionMethod(RetryTrait::class, 'canRetry'))->getFileName();
    }

    private static function usesRetryTrait(HandlerInterface $handler): bool
    {
        $pending = [];
        for ($class = get_class($handler); $class !== false; $class = get_parent_class($class)) {
            $pending = array_merge($pending, self::traits($class));
        }
        while (($trait = array_pop($pending)) !== null) {
            if ($trait === RetryTrait::class) {
                return true;
            }
            $pending = array_merge($pending, self::traits($trait));
        }
        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function traits(string $class): array
    {
        $traits = class_uses($class);
        return is_array($traits) ? $traits : [];
    }
}
