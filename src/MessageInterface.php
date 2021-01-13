<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

interface MessageInterface
{
    /**
     * Message identifier.
     *
     * This identifier should be unique all the time.
     * Recommendation is to use UUIDv4 (Included Message implementation
     * generating UUIDv4 identifiers)
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Message creation date - microtime(true)
     *
     * @return float
     */
    public function getCreated(): float;

    /**
     * Message executing date - microtime(true)
     *
     * @return float
     */
    public function getExecuteAt(): ?float;

    /**
     * Message type
     *
     * Based on this field, message will be dispatched and will be sent to
     * appropriate handler.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Payload data.
     *
     * This data can be used for anything that you would like to send to handler.
     * Warning! This data has to be serializable to string. Don't put there php resources
     * like database connection resources, file handlers etc..
     *
     * @return array<mixed>
     */
    public function getPayload(): ?array;

    /**
     * Total retries for message
     *
     * @return int
     */
    public function getRetries(): int;
}
