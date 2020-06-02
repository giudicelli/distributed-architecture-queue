<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Consumer;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\AbstractNetwork;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Exception\MustStopException;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Exception\NetworkException;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\ProtocolInterface;
use Psr\Log\LoggerInterface;

class Client extends AbstractNetwork
{
    protected $host = '';

    protected $connectTime = 0;
    protected $gotHandshake = false;

    public function __construct(LoggerInterface $logger, StoppableInterface $stoppable, ProtocolInterface $protocol, string $id, string $host, int $port, int $timeout = 5)
    {
        parent::__construct($logger, $stoppable, $protocol, $id, $port, $timeout);
        $this->host = $host;
    }

    public function clean(): void
    {
        parent::clean();
        $this->connectTime = 0;
        $this->gotHandshake = false;
    }

    public function run(callable $handleCallback): void
    {
        while (!$this->stoppable->mustStop()) {
            try {
                // Make sure we have a connection
                while (!$this->checkConnection()) {
                    // Wait before retrying
                    if (!$this->stoppable->sleep(5)) {
                        return;
                    }
                }
            } catch (MustStopException $e) {
                // We're supposed to stop, exit the loop
                break;
            }

            try {
                $job = $this->protocol->receiveJob($this->socket);
                if (!$job) {
                    // We didn't receive any data, wait a bit
                    $this->stoppable->sleep(1);
                } else {
                    call_user_func($handleCallback, $job);
                    $this->protocol->sendJobDone($this->socket);
                }
            } catch (NetworkException $e) {
                $this->logger->warning($e->getMessage());
                $this->clean();
            } catch (MustStopException $e) {
                // We're supposed to stop, exit the loop
                break;
            }
        }
        $this->clean();
    }

    protected function checkConnection(): bool
    {
        // I'm connected, but I never received the handshake,
        // something is wrong, restart the connection !
        if (!$this->gotHandshake && $this->connectTime &&
           (time() - $this->connectTime) > $this->timeout) {
            $this->clean();
        }

        if (!$this->socket) {
            if (!$this->initConnection()) {
                $this->clean();

                return false;
            }
        }

        // Initial handshake
        if (!$this->gotHandshake) {
            try {
                if ($this->protocol->receiveHandshake($this->socket)) {
                    $this->protocol->sendHandshake($this->socket);
                    $this->gotHandshake = true;
                }
            } catch (NetworkException $e) {
                $this->logger->warning($e->getMessage());
                $this->clean();

                return false;
            }
        }

        return true;
    }

    protected function initConnection(): bool
    {
        // Connect socket
        if (!$this->connectUnix()) {
            // Let's try TCP
            if (!$this->connectTCP()) {
                return false;
            }
        }
        $this->protocol->setSocketOptions($this->socket);
        $this->connectTime = time();

        return true;
    }

    protected function connectTCP(): bool
    {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            $this->logger->warning('Failed to create socket');

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1)) {
            $this->logger->warning('Failed to call socket_set_option SO_REUSEPORT');

            return false;
        }

        $timeout = ini_set('default_socket_timeout', $this->timeout);
        $result = @socket_connect($this->socket, $this->host, $this->port);
        ini_set('default_socket_timeout', $timeout);

        if ($this->stoppable->mustStop()) {
            return false;
        }
        if (!$result) {
            $this->logger->warning("Connect to {$this->host}:{$this->port} failed");

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1)) {
            $this->logger->warning('Failed to call socket_set_option SO_KEEPALIVE');

            return false;
        }
        $this->logger->debug("Connected to {$this->host}:{$this->port}");

        return true;
    }

    protected function connectUnix(): bool
    {
        $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$this->socket) {
            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1)) {
            $this->logger->warning('Failed to call socket_set_option SO_REUSEPORT');

            return false;
        }

        $timeout = ini_set('default_socket_timeout', $this->timeout);
        $result = @socket_connect($this->socket, $this->socketUnixPath);
        ini_set('default_socket_timeout', $timeout);
        if (!$result) {
            return false;
        }
        if ($this->stoppable->mustStop()) {
            return false;
        }
        $this->logger->debug("Connected to {$this->socketUnixPath}");

        return true;
    }

    /**
     * Returns the maximum size of a message in read().
     */
    protected function getMaxMessageSize(): int
    {
        return 1024 * 1024;
    }
}
