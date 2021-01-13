<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use DateTime;

/**
 * Class SharedFileShutdownTest
 *
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class SharedFileShutdownTest extends TestCase
{
    public function testShouldShutdownWithNotExistsingFile(): void
    {
        $sharedFileShutdown = new SharedFileShutdown('unknownfilepath.txt');
        $this->assertFalse($sharedFileShutdown->shouldShutdown(new DateTime()));
    }

    public function testShouldShutdownWithNewFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hermestest');
        if (!$file) {
            $this->markTestIncomplete("Cannot create tmp file");
        }
        $sharedFileShutdown = new SharedFileShutdown($file);
        $this->assertTrue($sharedFileShutdown->shouldShutdown(new DateTime('-3 minutes')));
    }

    public function testShouldShutdownWithOldFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hermestest');
        if (!$file) {
            $this->markTestIncomplete("Cannot create tmp file");
        }
        $sharedFileShutdown = new SharedFileShutdown($file);
        $this->assertFalse($sharedFileShutdown->shouldShutdown(new DateTime('+3 minutes')));
    }

    public function testShutdownCreatedCorrectFile(): void
    {
        $fileName = sys_get_temp_dir() . '/hermestest_shutdown_' . time();
        $sharedFileShutdown = new SharedFileShutdown($fileName);

        $this->assertFalse(file_exists($fileName));

        // try to initiate shutdown Hermes
        $shutdownTime = new DateTime();
        $this->assertTrue($sharedFileShutdown->shutdown($shutdownTime));

        $this->assertTrue(file_exists($fileName));

        $fileModificationTime = filemtime($fileName);
        $this->assertNotFalse($fileModificationTime);

        $this->assertEquals($shutdownTime->format('U'), $fileModificationTime);

        // clean after test
        unlink($fileName);
    }
}
