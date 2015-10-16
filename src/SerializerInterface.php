<?php

namespace Tomaj\Hermes;

interface SerializerInterface
{
    /**
     * Message serialization
     *
     * Method is used when serializing $message to driver.
     * Message has to be serializable to string.
     *
     * @return string
     */
    public function serialize(MessageInterface $message);

    /**
     * Opposite serialize method.
     *
     * Message needs to be unserialized from input string.
     * This string will be received from driver.
     *
     * @param string $string
     *
     * @return MessageInterface
     */
    public function unserialize($string);
}
