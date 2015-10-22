<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\SerializerInterface;

trait SerializerAwareTrait
{
	private $serializer;

	public function setSerializer(SerializerInterface $serializer)
	{
		$this->serializer = $serializer;
	}
}