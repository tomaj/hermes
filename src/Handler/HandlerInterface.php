<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

interface HandlerInterface
{
    /**
     * Basic handler function.
     *
     * You can implement you own handlers only with this function.
     * This function will be ne executed on web server so you can put there
     * long processing jobs that will be executed by dispatcher.
     * You have to register all your handlers to Dispatcher for specified types.
     *
     * @param MessageInterface $message
     *
     * @return bool
     */
    public function handle(MessageInterface $message): bool;
}
