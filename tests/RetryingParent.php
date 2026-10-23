<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;

abstract class RetryingParent implements HandlerInterface
{
    use RetryTrait;
}
