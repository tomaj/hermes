<?php

namespace Tomaj\Hermes;

interface SerializerInterface
{
    public function serialize(MessageInterface $message);

    public function unserialize($string);
}
