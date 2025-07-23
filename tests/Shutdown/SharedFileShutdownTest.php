<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;
use DateTime;

/**
 * @covers \Tomaj\Hermes\Shutdown\SharedFileShutdown
 */
class SharedFileShutdownTest extends TestCase
{
    private string $tempFile;
    
    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hermes_test_shutdown_');
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }
    
    public function testConstructor(): void
    {
        $shutdown = new SharedFileShutdown($this->tempFile);
        $this->assertInstanceOf(SharedFileShutdown::class, $shutdown);
    }
    
    public function testShouldShutdownWhenFileDoesNotExist(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        $startTime = new DateTime();
        
        $this->assertFalse($shutdown->shouldShutdown($startTime));
    }
    
    public function testShouldShutdownWhenFileIsOlder(): void
    {
        // Create file first
        touch($this->tempFile, time() - 3600); // 1 hour ago
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        $startTime = new DateTime(); // now
        
        $this->assertFalse($shutdown->shouldShutdown($startTime));
    }
    
    public function testShouldShutdownWhenFileIsNewer(): void
    {
        $startTime = new DateTime();
        sleep(1); // Wait a bit to ensure file is newer
        
        // Create file after start time
        touch($this->tempFile);
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        
        $this->assertTrue($shutdown->shouldShutdown($startTime));
    }
    
    public function testShutdownCreatesFile(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        $result = $shutdown->shutdown();
        
        $this->assertTrue($result);
        $this->assertTrue(file_exists($this->tempFile));
    }
    
    public function testShutdownWithSpecificTime(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        $shutdownTime = new DateTime('2023-01-01 12:00:00');
        $result = $shutdown->shutdown($shutdownTime);
        
        $this->assertTrue($result);
        $this->assertTrue(file_exists($this->tempFile));
        $this->assertEquals($shutdownTime->getTimestamp(), filemtime($this->tempFile));
    }
    
    public function testShutdownWithNullTime(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        
        $shutdown = new SharedFileShutdown($this->tempFile);
        $beforeTime = time();
        $result = $shutdown->shutdown(null);
        $afterTime = time();
        
        $this->assertTrue($result);
        $this->assertTrue(file_exists($this->tempFile));
        
        $fileTime = filemtime($this->tempFile);
        $this->assertGreaterThanOrEqual($beforeTime, $fileTime);
        $this->assertLessThanOrEqual($afterTime, $fileTime);
    }
}
