<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

interface SerializerInterface
{
    /**
     * Message serialization
     *
     * Method is used when serializing $message to driver.
     * Message has to be serializable to string.
     *
     * @param MessageInterface $message
     *
     * @throws SerializeException
     * @return string
     */
    public function serialize(MessageInterface $message): string;

    /**
     * Opposite serialize method.
     *
     * Message needs to be un-serialized from input string.
     * This string will be received from driver.
     *
     * @param string $string
     *
     * @throws SerializeException
     * @return MessageInterface
     */
    public function unserialize(string $string): MessageInterface;
}
