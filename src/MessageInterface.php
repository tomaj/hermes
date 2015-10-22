<?php

namespace Tomaj\Hermes;

interface MessageInterface
{
    /**
     * Message identifier.
     *
     * This identifier should be uniq all the time.
     * Recomendation is to use UUIDv4 (Included Message implementation
     * generating UUIDv4 identifiers )
     *
     * @return string
     */
    public function getId();

    /**
     * Message creation date - timestamp
     *
     * @return int
     */
    public function getCreated();

    /**
     * Message type
     *
     * Based on this field, message will be dispatched and will be sended to
     * appropriate handler.
     *
     * @return string
     */
    public function getType();

    /**
     * Payload data.
     *
     * This data can be used for anything that you would like to send to handler.
     * Warning! This data has to be serializable to string. Dont put there php resources
     * like database connection resources, file handlers etc..
     *
     * @return array
     */
    public function getPayload();
}
