<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Closure;
use ZMQSocket;

class ZeroMqDriver implements DriverInterface
{
    use MaxItemsTrait;
    use SerializerAwareTrait;

    /**
     * @var ZMQSocket
     */
    private $ZMQSocket;

    /**
     * @var integer
     */
    private $refreshInterval;

    /**
     * Create new ZeroMQ Driver
     *
     * Driver needs ZMQSocket intialised as server or client - depends on which functionality you need.
     *
     * If you initialize worker you have to provide ZMQSocket initialized
     * with ZMQ::SOCKET_REP and bind to specific port.
     * Example:
     *
     *   $context = new ZMQContext(1);
     *   $responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
     *   $responder->bind("tcp://*:5555");
     *   $driver = new ZeroMqDriver($responder);
     *
     * If you initialize emitter (usually in web thread) you have initialize
     * ZMQSocket with ZMQ::SOCKET_REQ and connect to specific worker port.
     * Example:
     *
     *   $context = new ZMQContext(1);
     *   $requester = new ZMQSocket($context, ZMQ::SOCKET_REQ);
     *   $requester->connect("tcp://localhost:5555");
     *   $driver = new ZeroMqDriver($requester);
     *
     * Warning! Be sure that you run worker command first and it is still running because in ZeroMQ
     * you web thread will stop and wait for worker which has to initialize ZeroMQ "server".
     *
     *
     * @see examples/zmq
     *
     * @param ZMQSocket         $ZMQSocket
     * @param integer|bool      $refreshInterval
     */
    public function __construct(ZMQSocket $ZMQSocket, int $refreshInterval = false)
    {
        $this->ZMQSocket = $ZMQSocket;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message): bool
    {
        $this->ZMQSocket->send($this->serializer->serialize($message));
        $reply = $this->ZMQSocket->recv();
        return $reply == 'ACK';
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback): void
    {
        while (true) {
            if (!$this->shouldProcessNext()) {
                break;
            }

            //  Wait for next request from client
            $request = $this->ZMQSocket->recv();

            $this->ZMQSocket->send('ACK');

            $callback($this->serializer->unserialize($request));
            $this->incrementProcessedItems();

            if ($this->refreshInterval) {
                sleep($this->refreshInterval);
            }
        }
    }
}
