<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;

/**
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class MessageSerializerTest extends TestCase
{
    public function testUnserializeWithInvalidJson(): void
    {
        $serializer = new MessageSerializer();
        
        $this->expectException(SerializeException::class);
        $this->expectExceptionMessage("Cannot unserialize message from 'invalid json'");
        
        $serializer->unserialize('invalid json');
    }
    
    public function testSerializeWithNonSerializableData(): void
    {
        $serializer = new MessageSerializer();
        
        // Create a message with non-serializable data (like a resource)
        $resource = fopen('php://memory', 'r');
        $message = $this->createMock(\Tomaj\Hermes\MessageInterface::class);
        $message->method('getId')->willReturn('test-id');
        $message->method('getType')->willReturn('test-type');
        $message->method('getPayload')->willReturn(['resource' => $resource]);
        $message->method('getCreated')->willReturn(microtime(true));
        $message->method('getExecuteAt')->willReturn(null);
        $message->method('getRetries')->willReturn(0);
        
        $this->expectException(SerializeException::class);
        $this->expectExceptionMessage("Cannot serialize message test-id");
        
        $serializer->serialize($message);
        
        fclose($resource);
    }
    
    public function testUnserializeWithMissingMessageKey(): void
    {
        $serializer = new MessageSerializer();
        
        $this->expectException(SerializeException::class);
        $this->expectExceptionMessage("Cannot unserialize message from '{\"data\":\"test\"}'");
        
        $serializer->unserialize('{"data":"test"}');
    }
    
    public function testUnserializeWithInvalidMessageFormat(): void
    {
        $serializer = new MessageSerializer();
        
        $this->expectException(SerializeException::class);
        $this->expectExceptionMessage("Invalid message format in '{\"message\":{\"type\":\"test\"}}'");
        
        $serializer->unserialize('{"message":{"type":"test"}}');
    }
}
