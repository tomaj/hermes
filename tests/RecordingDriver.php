<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Test\Driver\DummyDriver;

class RecordingDriver extends DummyDriver
{
    /** @var MessageInterface[] */
    public $sent = [];

    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $this->sent[] = $message;
        return true;
    }
}
