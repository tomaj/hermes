<?php
declare(strict_types=1);

namespace Tomaj\Hermes;

interface EmitterInterface
{
    /**
     * Emit new message
     *
     * @param MessageInterface  $message
     * @param int $priority
     *
     * @return $this
     */
    public function emit(MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM): EmitterInterface;
}
