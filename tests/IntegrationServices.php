<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\Assert;

/**
 * Where the integration tests find their brokers.
 *
 * HERMES_TEST_REDIS (e.g. tcp://127.0.0.1:6379), HERMES_TEST_RABBITMQ (a host name) and
 * HERMES_TEST_SQS (an SQS endpoint such as moto) enable the tests for that broker. A test whose
 * variable is unset is skipped, unless HERMES_REQUIRE_SERVICES=1: then it fails, so CI cannot
 * pass while a broker is silently missing.
 */
final class IntegrationServices
{
    public static function get(string $variable): string
    {
        $value = getenv($variable);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        self::unavailable("Set {$variable} to run this integration test.");
    }

    public static function unavailable(string $reason): never
    {
        if (self::required()) {
            Assert::fail("{$reason} HERMES_REQUIRE_SERVICES=1 turns this skip into a failure.");
        }

        Assert::markTestSkipped($reason);
    }

    public static function required(): bool
    {
        return getenv('HERMES_REQUIRE_SERVICES') === '1';
    }
}
