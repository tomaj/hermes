<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Tomaj\Hermes\Test\IntegrationServices
 */
class IntegrationServicesTest extends TestCase
{
    private string|false $required;

    #[\Override]
    protected function setUp(): void
    {
        $this->required = getenv('HERMES_REQUIRE_SERVICES');
    }

    #[\Override]
    protected function tearDown(): void
    {
        putenv('HERMES_TEST_EXAMPLE');
        putenv($this->required === false ? 'HERMES_REQUIRE_SERVICES' : "HERMES_REQUIRE_SERVICES={$this->required}");
    }

    public function testReturnsTheConfiguredValue(): void
    {
        putenv('HERMES_TEST_EXAMPLE=tcp://broker:1234');

        self::assertSame('tcp://broker:1234', IntegrationServices::get('HERMES_TEST_EXAMPLE'));
    }

    public function testServicesAreRequiredOnlyWithTheExactValueOne(): void
    {
        putenv('HERMES_REQUIRE_SERVICES');
        self::assertFalse(IntegrationServices::required());
        putenv('HERMES_REQUIRE_SERVICES=0');
        self::assertFalse(IntegrationServices::required());
        putenv('HERMES_REQUIRE_SERVICES=1');
        self::assertTrue(IntegrationServices::required());
    }
}
