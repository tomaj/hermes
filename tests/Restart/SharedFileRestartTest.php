<?php

namespace Tomaj\Hermes\Test;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Restart\SharedFileRestart;
use DateTime;

class SharedFileRestartTest extends PHPUnit_Framework_TestCase
{
    public function testWithNotExistsingFile()
    {
        $sharedRestart1 = new SharedFileRestart('unknownfilepath.txt');
        $this->assertFalse($sharedRestart1->shouldRestart(new DateTime()));
    }

    public function testWithNewFile()
    {
        $sharedRestart1 = new SharedFileRestart(tempnam(sys_get_temp_dir(), 'hermestest'));
        $this->assertTrue($sharedRestart1->shouldRestart(new DateTime('-3 minutes')));
    }

    public function testWithOldFile()
    {
        $sharedRestart1 = new SharedFileRestart(tempnam(sys_get_temp_dir(), 'hermestest'));
        $this->assertFalse($sharedRestart1->shouldRestart(new DateTime('+3 minutes')));
    }
}
