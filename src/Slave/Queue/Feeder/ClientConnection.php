<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\ProtocolInterface;

class ClientConnection
{
    const STATE_HANDSHAKE = 0;
    const STATE_READY = 1;
    const STATE_WAITING_ANSWER = 2;

    /** @var StoppableInterface */
    protected $stoppable;

    /** @var ProtocolInterface */
    protected $protocol;

    private $state;

    /** @var resource */
    private $socket;

    /** @var null|\JsonSerializable */
    private $job;

    public function __construct(StoppableInterface $stoppable, ProtocolInterface $protocol, $socket)
    {
        $this->stoppable = $stoppable;
        $this->protocol = $protocol;
        $this->socket = $socket;
        $this->state = self::STATE_HANDSHAKE;

        $this->protocol->setSocketOptions($this->socket);
        $this->protocol->sendHandshake($socket);
    }

    public function __destruct()
    {
        if ($this->socket) {
            @socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function setState(int $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function read(FeederInterface $feeder): void
    {
        switch ($this->state) {
            case self::STATE_HANDSHAKE:
                if (!$this->protocol->receiveHandshake($this->socket)) {
                    return;
                }
                $this->state = self::STATE_READY;

                break;
            case self::STATE_WAITING_ANSWER:
                try {
                    if (!$this->protocol->receiveJobDone($this->socket)) {
                        return;
                    }
                    $feeder->success($this->job);
                    $this->job = null;
                    $this->state = self::STATE_READY;
                } catch (\Exception $e) {
                    $feeder->error($this->job);
                    $this->job = null;

                    throw $e;
                }

                break;
        }
    }

    public function write(FeederInterface $feeder): bool
    {
        // Get a job
        $job = $feeder->get();
        if (!$job) {
            return false;
        }

        try {
            $this->protocol->sendJob($this->socket, $job);
        } catch (\Exception $e) {
            $feeder->error($job);

            throw $e;
        }

        $this->job = $job;
        $this->state = self::STATE_WAITING_ANSWER;

        return true;
    }
}
