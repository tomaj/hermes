<?php

namespace Tomaj\Hermes;

use Tomaj\Hermes\Handler\HandlerInterface;

interface EmitterInterface
{
    /**
     * Emit new message
     *
     * @param MessageInterface  $message
     *
     * @return $this
     */
    public function emit(MessageInterface $message);
}


