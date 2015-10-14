<?php

namespace Tomaj\Hermes;

interface MessageInterface
{
    public function getId();

    public function getCreated();

    public function getType();

    public function getPayload();
}
