<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use JsonException;

/**
 * Exception messages never contain the serialized string: it may hold personal data or tokens and
 * would end up in logs and error trackers. They name the problem, the length and a short SHA-256
 * prefix that matches the stored message (`hash('sha256', $string)`).
 */
final class MessageSerializer implements SerializerInterface
{
    #[\Override]
    public function serialize(MessageInterface $message): string
    {
        try {
            return json_encode([
                'message' => [
                    'id' => $message->getId(),
                    'type' => $message->getType(),
                    'created' => $message->getCreated(),
                    'payload' => $message->getPayload(),
                    'execute_at' => $message->getExecuteAt(),
                    'retries' => $message->getRetries(),
                ],
            ], JSON_INVALID_UTF8_IGNORE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializeException("Cannot serialize message {$message->getId()}: {$e->getMessage()}", $e->getCode(), previous: $e);
        }
    }

    #[\Override]
    public function unserialize(string $string): MessageInterface
    {
        try {
            $data = json_decode($string, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializeException('Cannot unserialize message: invalid JSON (' . $e->getMessage() . ') ' . $this->describe($string), $e->getCode(), previous: $e);
        }
        if (!is_array($data) || !isset($data['message'])) {
            throw new SerializeException('Cannot unserialize message: no "message" key ' . $this->describe($string));
        }
        $message = $data['message'];
        if (!is_array($message) || !isset($message['type'], $message['id'], $message['created'])) {
            throw new SerializeException('Invalid message format: "message" needs type, id and created ' . $this->describe($string));
        }

        $type = $message['type'];
        $id = $message['id'];
        $created = $message['created'];
        if (!is_string($type) || !is_string($id) || !is_numeric($created)) {
            throw new SerializeException('Invalid message format: type and id must be strings and created a number ' . $this->describe($string));
        }

        $executeAt = null;
        if (isset($message['execute_at']) && is_numeric($message['execute_at'])) {
            $executeAt = (float) $message['execute_at'];
        }

        $retries = 0;
        if (isset($message['retries']) && is_numeric($message['retries'])) {
            $retries = (int) $message['retries'];
        }

        $payload = null;
        if (isset($message['payload']) && is_array($message['payload'])) {
            $payload = $message['payload'];
        }

        return new Message($type, $payload, $id, (float) $created, $executeAt, $retries);
    }

    private function describe(string $string): string
    {
        return '(' . strlen($string) . ' bytes, sha256 ' . substr(hash('sha256', $string), 0, 16) . ')';
    }
}
