<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\HermesException;

/**
 * Kafka did not confirm a sent message, or the consumer reported an error.
 */
final class KafkaDriverException extends HermesException {}
