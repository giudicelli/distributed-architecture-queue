<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\AbstractNetwork;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\ProtocolInterface;

class Server extends AbstractNetwork
{
    protected $bindTo = '';
    protected $socketUnix;

    /** @var array<ClientConnection> */
    protected $connections = [];

    public function __construct(StoppableInterface $stoppable, ProtocolInterface $protocol, string $id, string $bindTo, int $port, int $timeout = 5)
    {
        parent::__construct($stoppable, $protocol, $id, $port, $timeout);
        @unlink($this->socketUnixPath);
        $this->bindTo = $bindTo;
    }

    public function clean(): void
    {
        parent::clean();
        $this->closeServerSockets();

        if ($this->connections) {
            $this->connections = [];
        }
        @unlink($this->socketUnixPath);
    }

    public function run(FeederInterface $feeder): void
    {
        if ($this->stoppable->mustStop()) {
            return;
        }

        // Try to start the server
        while (!$this->listen()) {
            $this->clean();
            if (!$this->stoppable->sleep(5)) {
                return;
            }
        }

        echo "{level:info}Waiting for new connections\n";

        for (;;) {
            // Get new incoming connections
            $newConnections = $this->getNewConnections();
            if ($this->stoppable->mustStop()) {
                break;
            }

            // No connected sockets
            if (empty($this->connections)) {
                if (!$this->stoppable->sleep(1)) {
                    break;
                }

                continue;
            }

            $readStatus = $this->read($feeder);
            if ($this->stoppable->mustStop()) {
                break;
            }

            if ($feeder->empty()) {
                $writeStatus = false;
            } else {
                // While we get new connections, we must limit
                // the number of consumers to send jobs to,
                // or the new connections might timeout on client side,
                // we must always favorize new connections
                $writeStatus = $this->write($feeder, 0 === $newConnections ? 0 : 10);

                if ($feeder->empty()) {
                    // There is job available at the time
                    echo "{level:notice}Feeder queue is empty\n";
                }
                if ($this->stoppable->mustStop()) {
                    break;
                }
            }

            if (!$newConnections && !$readStatus && !$writeStatus) {
                $this->stoppable->sleep(2);
            } else {
                $this->stoppable->ping();
            }
        }

        $this->cleanStop($feeder);
    }

    protected function closeServerSockets(): void
    {
        parent::clean();

        if ($this->socketUnix) {
            @socket_close($this->socketUnix);
            $this->socketUnix = null;
        }
    }

    protected function cleanStop(FeederInterface $feeder): void
    {
        // First we close all server sockets
        $this->closeServerSockets();

        $t = time();
        do {
            // Close all connections that are not in state ClientConnection::STATE_WAITING_ANSWER
            foreach ($this->connections as $id => $connection) {
                if (ClientConnection::STATE_WAITING_ANSWER !== $connection->getState()) {
                    unset($this->connections[$id]);
                }
            }
            $this->read($feeder);
        } while (!empty($this->connections) && (time() - $t) < $this->timeout);

        $this->clean();
    }

    protected function hasWaitingForAnswerConnections(): bool
    {
        foreach ($this->connections as $connection) {
            if (ClientConnection::STATE_WAITING_ANSWER === $connection->getState()) {
                return true;
            }
        }

        return false;
    }

    protected function read(FeederInterface $feeder): bool
    {
        // Get connected sockets state
        $r = [];
        foreach ($this->connections as $connection) {
            // We're expecting something from this socket
            if (ClientConnection::STATE_READY !== $connection->getState()) {
                $r[] = $connection->getSocket();
            }
        }
        if (!$r) {
            return false;
        }

        $w = null;
        $e = null;
        if (false === ($num = @socket_select($r, $w, $e, 0))) {
            // An error
            if ($this->protocol->isErrorIgnorable()) {
                $err = socket_strerror(socket_last_error());
                echo "{level:warning}socket_select failed for readable sockets: {$err}\n";
            }

            return false;
        }

        if (!$num || empty($r)) {
            // No socket available for reading
            return false;
        }

        foreach ($this->connections as $id => $connection) {
            // Do we have data to read from this connection?
            if (!in_array($connection->getSocket(), $r)) {
                continue;
            }

            try {
                $connection->read($feeder);
            } catch (\Exception $e) {
                $connection = null;
                unset($this->connections[$id]);
                echo "{level:warning}{$e->getMessage()}, closing connection\n";
            }
        }

        return true;
    }

    protected function write(FeederInterface $feeder, int $limitCount = 0): bool
    {
        /** @var array<ClientConnection> */
        $availableConnections = [];
        foreach ($this->connections as $connection) {
            if (ClientConnection::STATE_READY === $connection->getState()) {
                $availableConnections[] = $connection;
            }
        }
        if (!$availableConnections) {
            return false;
        }

        shuffle($availableConnections);

        if ($limitCount) {
            $availableConnections = array_slice($availableConnections, 0, $limitCount);
        }
        echo '{level:debug}Available consumers: '.count($availableConnections).' / '.count($this->connections)."\n";

        foreach ($availableConnections as $id => $connection) {
            if ($feeder->empty()) {
                break;
            }

            try {
                if (!$connection->write($feeder)) {
                    break;
                }
            } catch (\Exception $e) {
                $connection = null;
                unset($this->connections[$id]);
                echo "{level:warning}{$e->getMessage()}, closing connection\n";
            }

            // Were we asked to stop?
            if ($this->stoppable->mustStop()) {
                break;
            }
        }

        return true;
    }

    protected function listen(): bool
    {
        // First listen on the UNIX socket
        if (!$this->listenUnix()) {
            if ($this->socketUnix) {
                @socket_close($this->socketUnix);
                $this->socketUnix = null;
            }
        }
        // Now listen on the TCP socket
        return $this->listenTCP();
    }

    protected function listenUnix(): bool
    {
        $this->socketUnix = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$this->socketUnix) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Failed to create UNIX socket: {$err}\n";

            return false;
        }
        if (!@socket_set_option($this->socketUnix, SOL_SOCKET, SO_REUSEPORT, 1)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Failed to call socket_set_option SO_REUSEPORT: {$err}\n";

            return false;
        }

        @unlink($this->socketUnixPath);
        if (!@socket_bind($this->socketUnix, $this->socketUnixPath)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Could not bind to {$this->socketUnixPath}: {$err}\n";

            return false;
        }
        chmod($this->socketUnixPath, 0777);

        if (!@socket_listen($this->socketUnix, 1024)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Could not listen on {$this->socketUnixPath}: {$err}\n";

            return false;
        }

        return true;
    }

    protected function listenTCP(): bool
    {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Failed to create TCP socket: {$err}\n";

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Failed to call socket_set_option SO_REUSEPORT: {$err}\n";

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:warning}Failed to call socket_set_option SO_REUSEADDR: {$err}\n";

            return false;
        }
        $bindTo = '0.0.0.0';
        if ($this->bindTo) {
            $bindTo = $this->bindTo;
        }
        if (!@socket_bind($this->socket, $bindTo, $this->port)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Could not bind to {$bindTo}:{$this->port}: {$err}\n";

            return false;
        }
        if (!@socket_listen($this->socket, 1024)) {
            $err = socket_strerror(socket_last_error());
            echo "{level:error}Could not listen on {$bindTo}:{$this->port}: {$err}\n";

            return false;
        }

        return true;
    }

    protected function getNewConnections(int $timeout = 10000): int
    {
        // Accept all incoming connections
        $newConnections = 0;
        do {
            $r = [$this->socket, $this->socketUnix];
            $w = null;
            $e = null;
            $num = 0;
            if (($num = @socket_select($r, $w, $e, 0, $timeout)) > 0) {
                foreach ($r as $s) {
                    $socket = @socket_accept($s);
                    if ($socket) {
                        try {
                            $this->connections[] = new ClientConnection($this->stoppable, $this->protocol, $socket);
                            ++$newConnections;
                        } catch (\Exception $e) {
                            echo  '{level:warning}'.$e->getMessage()."\n";
                        }
                    }
                }
            } elseif (false === $num) {
                if ($this->protocol->isErrorIgnorable()) {
                    $err = socket_strerror(socket_last_error());
                    echo "{level:warning}socket_select failed: {$err}\n";
                }
            }
        } while ($num > 0);

        return $newConnections;
    }
}
