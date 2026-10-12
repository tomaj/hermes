<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Double;

use Aws\Sqs\SqsClient;

/**
 * SQS operations are magic methods on the AWS client. PHPUnit can only mock methods that exist.
 */
class SqsClientDouble extends SqsClient
{
    /**
     * @param array<string, mixed> $args
     */
    public function createQueue(array $args = []): mixed
    {
        return null;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function sendMessage(array $args = []): mixed
    {
        return null;
    }
}
