<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

class MessageSerializer implements SerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize(MessageInterface $message): string
    {
        $result = json_encode([
            'message' => [
                'id' => $message->getId(),
                'type' => $message->getType(),
                'created' => $message->getCreated(),
                'payload' => $message->getPayload(),
                'execute_at' => $message->getExecuteAt(),
                'retries' => $message->getRetries(),
            ]
        ], JSON_INVALID_UTF8_IGNORE);
        if ($result === false) {
            throw new SerializeException('Cannot serialize message ' . $message->getId());
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize(string $string): MessageInterface
    {
        $data = json_decode($string, true);
        if (!is_array($data) || !isset($data['message'])) {
            throw new SerializeException(sprintf("Cannot unserialize message from '%s'", $string));
        }

        $message = $data['message'];
        if (!is_array($message) || !isset($message['type'], $message['id'], $message['created'])) {
            throw new SerializeException(sprintf("Invalid message format in '%s'", $string));
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

        if (!is_string($message['type'])) {
            throw new SerializeException(sprintf("Invalid message type in '%s'", $string));
        }

        $created = null;
        if (is_numeric($message['created'])) {
            $created = (float) $message['created'];
        }

        $id = null;
        if (is_string($message['id'])) {
            $id = $message['id'];
        }

        return new Message($message['type'], $payload, $id, $created, $executeAt, $retries);
    }
}
