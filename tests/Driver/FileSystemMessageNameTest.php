<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\FileSystemMessageName;

/**
 * @covers \Tomaj\Hermes\Driver\FileSystemMessageName
 */
final class FileSystemMessageNameTest extends TestCase
{
    public function testNameStartsWithTheZeroPaddedDueTime(): void
    {
        self::assertSame('1700000000.250000-abc.json', FileSystemMessageName::create(1700000000.25, 'abc'));
        self::assertSame('0000000012.500000-abc.json', FileSystemMessageName::create(12.5, 'abc'));
        self::assertSame('0000000000.000000-abc.json', FileSystemMessageName::create(-3.0, 'abc'));
        self::assertSame('9999999999.500000-abc.json', FileSystemMessageName::create(9999999999.5, 'abc'));
    }

    public function testNamesSortByDueTime(): void
    {
        $names = [
            FileSystemMessageName::create(1700000000.5, 'b'),
            FileSystemMessageName::create(99.0, 'c'),
            FileSystemMessageName::create(1700000000.25, 'a'),
        ];
        sort($names, SORT_STRING);
        self::assertSame(['0000000099.000000-c.json', '1700000000.250000-a.json', '1700000000.500000-b.json'], $names);
    }

    public function testIsMessage(): void
    {
        self::assertTrue(FileSystemMessageName::isMessage('1.000000-a.json'));
        self::assertFalse(FileSystemMessageName::isMessage('1.000000-a.json.part'));
        self::assertFalse(FileSystemMessageName::isMessage('.'));
    }

    public function testIsDueAtAndAfterTheDueTime(): void
    {
        $name = FileSystemMessageName::create(1700000000.25, 'abcdef0123456789');
        self::assertFalse(FileSystemMessageName::isDue($name, 1700000000.249999));
        self::assertTrue(FileSystemMessageName::isDue($name, 1700000000.25));
        self::assertTrue(FileSystemMessageName::isDue($name, 1700000001.0));
    }

    public function testIsExpiredOnlyAfterTheTimeout(): void
    {
        self::assertFalse(FileSystemMessageName::isExpired(1000, 1060, 60));
        self::assertTrue(FileSystemMessageName::isExpired(1000, 1061, 60));
        self::assertFalse(FileSystemMessageName::isExpired(1000, 1000, 0));
        self::assertTrue(FileSystemMessageName::isExpired(1000, 1001, 0));
    }

    public function testClaimedNameKeepsTheClaimTimeAndTheName(): void
    {
        $claimed = FileSystemMessageName::claimed('1700000000.250000-ab.json', 1760000000);

        self::assertSame('1760000000~1700000000.250000-ab.json', $claimed);
        self::assertSame(1760000000, FileSystemMessageName::claimTime($claimed));
        self::assertSame('1700000000.250000-ab.json', FileSystemMessageName::unclaimed($claimed));
        self::assertTrue(FileSystemMessageName::isMessage($claimed));
    }

    public function testUnclaimedNameWithoutAClaimIsUnchanged(): void
    {
        self::assertSame('1700000000.250000-ab.json', FileSystemMessageName::unclaimed('1700000000.250000-ab.json'));
        self::assertSame('a~b.json', FileSystemMessageName::unclaimed('5~a~b.json'));
    }
}
