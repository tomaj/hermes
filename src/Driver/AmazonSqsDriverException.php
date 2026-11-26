<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\HermesException;

/**
 * Amazon SQS returned no queue URL for the createQueue call in the AmazonSqsDriver constructor.
 */
final class AmazonSqsDriverException extends HermesException {}
