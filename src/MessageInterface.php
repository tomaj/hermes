<?php

namespace Tomaj\Hermes;

interface MessageInterface
{
    public function getType();

    public function getPayload();
}
