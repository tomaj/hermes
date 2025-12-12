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
     *
     * @throws SerializeException
     */
    public function serialize(MessageInterface $message): string;

    /**
     * Opposite serialize method.
     *
     * Message needs to be un-serialized from input string.
     * This string will be received from driver.
     *
     *
     * @throws SerializeException
     */
    public function unserialize(string $string): MessageInterface;
}
