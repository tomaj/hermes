<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use Aws\Result;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Test\Double\SqsClientDouble;

/**
 * @covers \Tomaj\Hermes\Driver\AmazonSqsDriver
 */
class AmazonSqsDriverAttributesTest extends TestCase
{
    public function testQueueAttributesAreSentAsStrings(): void
    {
        $client = $this->getMockBuilder(SqsClientDouble::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueue'])
            ->getMock();
        $client->expects(self::once())->method('createQueue')
            ->with(self::identicalTo([
                'QueueName' => 'test-queue',
                'Attributes' => ['VisibilityTimeout' => '30', 'FifoQueue' => '1', 'DelaySeconds' => '0.5', 'Policy' => ''],
            ]))
            ->willReturn(new Result(['QueueUrl' => 'https://sqs.region.amazonaws.com/123456789/test-queue']));

        new AmazonSqsDriver(
            $client,
            'test-queue',
            ['VisibilityTimeout' => 30, 'FifoQueue' => true, 'DelaySeconds' => 0.5, 'Policy' => null],
        );
    }
}
