<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\HermesException;

/**
 * Concrete classes are final and every method that implements an interface or parent method has #[\Override].
 */
#[CoversNothing]
class ArchitectureTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function classes(): iterable
    {
        $src = __DIR__ . '/../src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $class = 'Tomaj\\Hermes\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($src) + 1));
            if (class_exists($class)) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classes')]
    public function testConcreteClassesAreFinal(string $class): void
    {
        $reflection = new ReflectionClass($class);
        if ($class === HermesException::class) {
            self::assertFalse($reflection->isFinal(), 'HermesException is the base exception and stays open');
            return;
        }
        self::assertTrue($reflection->isFinal(), "{$class} must be final");
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classes')]
    public function testImplementedMethodsHaveOverride(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $missing = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
                continue;
            }
            $inherited = false;
            foreach ($reflection->getInterfaces() as $interface) {
                $inherited = $inherited || $interface->hasMethod($method->getName());
            }
            $parent = $reflection->getParentClass();
            $inherited = $inherited || ($parent !== false && $parent->hasMethod($method->getName()));
            if (!$inherited) {
                continue;
            }
            if ($method->getAttributes(\Override::class) === []) {
                $missing[] = "{$class}::{$method->getName()}()";
            }
        }
        self::assertSame([], $missing, 'These methods must have #[\\Override]');
    }

    public function testBufferedEmitterIsFinalWithOverrideOnEmitAndCount(): void
    {
        $reflection = new ReflectionClass(BufferedEmitter::class);
        self::assertTrue($reflection->isFinal());
        foreach (['emit', 'count'] as $method) {
            self::assertNotSame([], $reflection->getMethod($method)->getAttributes(\Override::class), "BufferedEmitter::{$method}() must have #[\\Override]");
        }
    }
}
