<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder;

use giudicelli\DistributedArchitectureQueue\Slave\Queue\AbstractNetwork;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Helper;

class ClientConnection
{
    const STATE_HANDSHAKE = 0;
    const STATE_READY = 1;
    const STATE_WAITING_ANSWER = 2;

    private $state;

    /** @var resource */
    private $socket;

    /** @var \JsonSerializable */
    private $job;

    public function __construct($socket)
    {
        $this->socket = $socket;
        $this->state = self::STATE_HANDSHAKE;

        $this->setsocketOptions();
        $this->sendHandshake();
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
        // We're expecting an answer from client
        $result = @socket_read($this->socket, 8192, PHP_BINARY_READ);

        // An error
        if (false === $result && !Helper::isIgnorableSocketErrors($this->socket)) {
            // Connection was closed while we were waiting
            // for a job answer
            if (self::STATE_WAITING_ANSWER === $this->state) {
                $feeder->error($this->job);
            }

            throw new \Exception('Connection closed');
        }

        // No data available
        if (!$result) {
            return;
        }

        $result = trim($result);

        if (self::STATE_HANDSHAKE === $this->state) {
            if (AbstractNetwork::COMMAND_HANDSHAKE !== $result) {
                throw new \Exception('Invalid command while waiting for handshake');
            }
        } elseif (self::STATE_WAITING_ANSWER === $this->state) {
            if (AbstractNetwork::COMMAND_DONE !== $result) {
                $feeder->error($this->job);

                throw new \Exception('Invalid command while waiting for answer');
            }
            $feeder->success($this->job);
            $this->job = null;
        }
        $this->state = self::STATE_READY;
    }

    public function write(FeederInterface $feeder): bool
    {
        // Get a job
        $job = $feeder->get();
        if (!$job) {
            return false;
        }

        // Send the job
        $jobStr = json_encode($job);
        if (!Helper::socketSend($this->socket, $jobStr)) {
            $feeder->error($job);

            throw new \Exception('Connection closed while sending a job');
        }
        $this->job = $job;
        $this->state = self::STATE_WAITING_ANSWER;

        return true;
    }

    protected function setsocketOptions(): void
    {
        if (!@socket_set_nonblock($this->socket)) {
            throw new \Exception('Failed to call socket_set_nonblock');
        }
        $linger = ['l_linger' => 0, 'l_onoff' => 1];
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $linger)) {
            throw new \Exception('Failed to call socket_set_option SO_LINGER on new socket');
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1)) {
            throw new \Exception('Failed to call socket_set_option SO_KEEPALIVE on new socket');
        }
        // Will fail in case of a Unix socket, but it's ok
        @socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1);
    }

    protected function sendHandshake(): void
    {
        if (!Helper::socketSend($this->socket, AbstractNetwork::COMMAND_HANDSHAKE)) {
            throw new \Exception('Connection was closed while sending handshake');
        }
    }
}
