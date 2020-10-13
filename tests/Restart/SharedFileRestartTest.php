<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Restart\SharedFileRestart;
use DateTime;

class SharedFileRestartTest extends TestCase
{
    public function testShouldRestartWithNotExistsingFile()
    {
        $sharedFileRestart = new SharedFileRestart('unknownfilepath.txt');
        $this->assertFalse($sharedFileRestart->shouldRestart(new DateTime()));
    }

    public function testShouldRestartWithNewFile()
    {
        $sharedFileRestart = new SharedFileRestart(tempnam(sys_get_temp_dir(), 'hermestest'));
        $this->assertTrue($sharedFileRestart->shouldRestart(new DateTime('-3 minutes')));
    }

    public function testShouldRestartWithOldFile()
    {
        $sharedFileRestart = new SharedFileRestart(tempnam(sys_get_temp_dir(), 'hermestest'));
        $this->assertFalse($sharedFileRestart->shouldRestart(new DateTime('+3 minutes')));
    }

    public function testRestartCreatedCorrectFile()
    {
        $fileName = sys_get_temp_dir() . '/hermestest_restart_' . time();
        $sharedFileRestart = new SharedFileRestart($fileName);

        $this->assertFalse(file_exists($fileName));

        // try to initiate restart Hermes
        $restartTime = new DateTime();
        $this->assertTrue($sharedFileRestart->restart($restartTime));

        $this->assertTrue(file_exists($fileName));

        $fileModificationTime = filemtime($fileName);
        $this->assertNotFalse($fileModificationTime);

        $this->assertEquals($restartTime->format('U'), $fileModificationTime);

        // clean after test
        unlink($fileName);
    }
}
