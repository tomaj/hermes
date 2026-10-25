<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;

/**
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class MessageSerializerTest extends TestCase
{
    public function testSerializeWithNonSerializableData(): void
    {
        $serializer = new MessageSerializer();

        // Create a message with non-serializable data (like a resource)
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        $message = self::createStub(MessageInterface::class);
        $message->method('getId')->willReturn('test-id');
        $message->method('getType')->willReturn('test-type');
        $message->method('getPayload')->willReturn(['resource' => $resource]);
        $message->method('getCreated')->willReturn(microtime(true));
        $message->method('getExecuteAt')->willReturn(null);
        $message->method('getRetries')->willReturn(0);

        try {
            $serializer->serialize($message);
            self::fail('a resource cannot be serialized');
        } catch (SerializeException $e) {
            self::assertSame('Cannot serialize message test-id: Type is not supported', $e->getMessage());
            self::assertSame(JSON_ERROR_UNSUPPORTED_TYPE, $e->getCode());
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        } finally {
            fclose($resource);
        }
    }

    /**
     * The text of json_last_error_msg() differs between PHP versions
     * (8.6 appends "near location <line>:<column>"), so take it from the running PHP.
     */
    private static function jsonError(string $string): string
    {
        try {
            json_decode($string, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $e->getMessage();
        }
        throw new \LogicException('the string must be invalid JSON: ' . $string);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenMessages(): iterable
    {
        yield 'invalid JSON' => ['invalid json secret-token', 'Cannot unserialize message: invalid JSON (' . self::jsonError('invalid json secret-token') . ')'];
        yield 'JSON null' => ['null', 'Cannot unserialize message: no "message" key'];
        yield 'scalar' => ['"secret-token"', 'Cannot unserialize message: no "message" key'];
        yield 'no message key' => ['{"data":"secret-token"}', 'Cannot unserialize message: no "message" key'];
        yield 'message not an object' => ['{"message":"secret-token"}', 'Invalid message format: "message" needs type, id and created'];
        yield 'missing id and created' => ['{"message":{"type":"secret-token"}}', 'Invalid message format: "message" needs type, id and created'];
        yield 'missing created' => ['{"message":{"type":"t","id":"secret-token"}}', 'Invalid message format: "message" needs type, id and created'];
        yield 'type not a string' => ['{"message":{"type":1,"id":"secret-token","created":1}}', 'Invalid message format: type and id must be strings and created a number'];
        yield 'id not a string' => ['{"message":{"type":"secret-token","id":1,"created":1}}', 'Invalid message format: type and id must be strings and created a number'];
        yield 'created not a number' => ['{"message":{"type":"t","id":"i","created":"secret-token"}}', 'Invalid message format: type and id must be strings and created a number'];
    }

    #[DataProvider('brokenMessages')]
    public function testBrokenMessagesAreDescribedWithoutTheirContent(string $string, string $reason): void
    {
        $expected = $reason . ' (' . strlen($string) . ' bytes, sha256 ' . substr(hash('sha256', $string), 0, 16) . ')';

        try {
            new MessageSerializer()->unserialize($string);
            self::fail('the string must be rejected');
        } catch (SerializeException $e) {
            self::assertSame($expected, $e->getMessage());
            self::assertStringNotContainsString('secret-token', $e->getMessage());
        }
    }

    /**
     * The examples in UPGRADE-6.0.md, with literal hashes.
     */
    public function testDocumentedExampleMessages(): void
    {
        $serializer = new MessageSerializer();
        foreach ([
            '{"message":' => 'Cannot unserialize message: invalid JSON (' . self::jsonError('{"message":') . ') (11 bytes, sha256 846579511e89879b)',
            '{"message":{"id":"1"}}' => 'Invalid message format: "message" needs type, id and created (22 bytes, sha256 689d9dd54d900a86)',
        ] as $string => $expected) {
            try {
                $serializer->unserialize($string);
                self::fail('Expected SerializeException for ' . $string);
            } catch (SerializeException $e) {
                self::assertSame($expected, $e->getMessage());
            }
        }
    }

    public function testInvalidJsonKeepsTheJsonExceptionAsPrevious(): void
    {
        try {
            new MessageSerializer()->unserialize('{"message":');
            self::fail('the string must be rejected');
        } catch (SerializeException $e) {
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
            self::assertSame(JSON_ERROR_SYNTAX, $e->getCode(), 'the code is the json_last_error() code');
            self::assertStringStartsWith('Cannot unserialize message: invalid JSON (Syntax error', $e->getMessage());
        }
    }

    public function testValidMessagesHaveNoPreviousErrorLeftOver(): void
    {
        $serializer = new MessageSerializer();
        $message = $serializer->unserialize('{"message":{"type":"t","id":"i","created":1.5}}');

        self::assertSame('t', $message->getType());
        self::assertSame('i', $message->getId());
        self::assertSame(1.5, $message->getCreated());
    }
}
