<?php

namespace Tomaj\Hermes;

class MessageSerializer implements SerializerInterface
{
    public function serialize(MessageInterface $message)
    {
        return json_encode([
            'message' => [
                'id' => $message->getId(),
                'type' => $message->getType(),
                'created' => $message->getCreated(),
                'payload' => $message->getPayload(),
            ]
        ]);
    }

    public function unserialize($string)
    {
        $data = json_decode($string, true);
        // todo check if data is OK
        $message = $data['message'];
        return new Message($message['type'], $message['payload'], $message['id'], $message['created']);
    }
}
