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
        $result =  json_encode([
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
            throw new SerializeException("Cannot serialize message {$message->getId()}");
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize(string $string): MessageInterface
    {
        $data = json_decode($string, true);
        if ($data === null || $data === false) {
            throw new SerializeException("Cannot unserialize message from '{$string}'");
        }
        $message = $data['message'];
        $executeAt = null;
        if (isset($message['execute_at'])) {
            $executeAt = floatval($message['execute_at']);
        }
        $retries = 0;
        if (isset($message['retries'])) {
            $retries = intval($message['retries']);
        }
        return new Message($message['type'], $message['payload'], $message['id'], $message['created'], $executeAt, $retries);
    }
}
