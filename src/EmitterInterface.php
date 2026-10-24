<?php

declare(strict_types=1);

namespace Tomaj\Hermes;

use Tomaj\Hermes\Driver\UnknownPriorityException;

interface EmitterInterface
{
    /**
     * Emit new message
     *
     * @throws UnknownPriorityException
     * @throws SerializeException
     * @return $this
     */
    public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface;
}
