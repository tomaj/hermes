<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\SerializerInterface;

trait SerializerAwareTrait
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Set serializer to driver
     *
     * You can this trait to set serializer from outsite to your driver
     * if you need your custom serialization for your objects.
     *
     * @param SerializerInterface   $serializer
     *
     * @return void
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
