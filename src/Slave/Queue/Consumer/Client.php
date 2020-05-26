<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Consumer;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\AbstractNetwork;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Helper;

class Client extends AbstractNetwork
{
    protected $host = '';

    protected $connectTime = 0;
    protected $gotHandshake = false;

    public function __construct(StoppableInterface $stoppable, string $id, string $host, int $port, int $timeout = 5)
    {
        parent::__construct($stoppable, $id, $port, $timeout);
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
            // Make sure we have a connection
            while (!$this->checkConnection()) {
                // Wait before retrying
                if (!$this->stoppable->sleep(5)) {
                    return;
                }
            }

            $data = '';
            switch ($this->read($data)) {
                case -1:
                    // An error occured or we're supposed to reconnect
                    $this->clean();
                    // Wait a bit before reconnecting
                    $this->stoppable->sleep(1);

                break;
                case 0:
                    // No data
                    $this->stoppable->sleep(1);

                break;
                default:
                    // Got data
                    $item = json_decode($data, true);
                    call_user_func($handleCallback, $item);

                    // We let the feeder know we're done
                    if (!Helper::socketSend($this->socket, self::COMMAND_DONE)) {
                        $err = socket_strerror(socket_last_error($this->socket));
                        echo "{level:warning}Connection closed while sending COMMAND_DONE: {$err}\n";
                        $this->clean();
                    }

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
            if (!$this->readHandshake()) {
                $this->clean();

                return false;
            }
            // If we haven't received the handshake yet,
            // we're not fully connected
            if (!$this->gotHandshake) {
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
        if (!@socket_set_nonblock($this->socket)) {
            echo "{level:warning}Failed to call socket_set_nonblock\n";

            return false;
        }

        $linger = ['l_linger' => 0, 'l_onoff' => 1];
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $linger)) {
            echo "{level:warning}Failed to call socket_set_option SO_LINGER\n";

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1)) {
            echo "{level:warning}Failed to call socket_set_option SO_KEEPALIVE\n";

            return false;
        }
        $this->connectTime = time();

        return true;
    }

    protected function readHandshake(): bool
    {
        $buff = @socket_read($this->socket, 8192, PHP_BINARY_READ);
        if ($this->stoppable->mustStop()) {
            return false;
        }
        if (!$buff) {
            if (false === $buff && !Helper::isIgnorableSocketErrors()) {
                echo '{level:warning}Connection closed receiving handshake: '.socket_strerror(socket_last_error($this->socket))."\n";

                return false;
            }
            // Not an error, we just haven't received the handshake yet
            return true;
        }
        if (self::COMMAND_HANDSHAKE != trim($buff)) {
            echo "{level:warning}We didn't receive a proper handshake: ".trim($buff)."\n";

            return false;
        }
        // Send Handshake back
        if (!Helper::socketSend($this->socket, self::COMMAND_HANDSHAKE)) {
            $err = socket_strerror(socket_last_error($this->socket));
            echo "{level:warning}Connection closed sending handshake back: {$err}\n";

            return false;
        }

        $this->gotHandshake = true;

        return true;
    }

    protected function connectTCP(): bool
    {
        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->socket) {
            echo "{level:warning}Failed to create socket\n";

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1)) {
            echo "{level:warning}Failed to call socket_set_option SO_REUSEPORT\n";

            return false;
        }

        $timeout = ini_set('default_socket_timeout', $this->timeout);
        $result = @socket_connect($this->socket, $this->host, $this->port);
        ini_set('default_socket_timeout', $timeout);

        if ($this->stoppable->mustStop()) {
            return false;
        }
        if (!$result) {
            echo "{level:warning}Connect to {$this->host}:{$this->port} failed\n";

            return false;
        }
        if (!@socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1)) {
            echo "{level:warning}Failed to call socket_set_option SO_KEEPALIVE\n";

            return false;
        }
        echo "{level:debug}Connected to {$this->host}:{$this->port}\n";

        return true;
    }

    protected function connectUnix(): bool
    {
        $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$this->socket) {
            return false;
        }
        if (!@socket_set_option($this->socket, SOL_SOCKET, SO_REUSEPORT, 1)) {
            echo "{level:warning}Failed to call socket_set_option SO_REUSEPORT\n";

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
        echo "{level:debug}Connected to {$this->socketUnixPath}\n";

        return true;
    }

    protected function read(string &$data): int
    {
        $data = '';
        $len = 0;
        $t = 0;
        do {
            $buff = @socket_read($this->socket, 8192, PHP_BINARY_READ);
            if ($this->stoppable->mustStop()) {
                return -2;
            }

            if ($buff) {
                $data .= $buff;
                $len = strlen($data);
                if ($len > $this->getMaxMessageSize()) {
                    // Request connection close
                    echo "{level:error}Message is too big.\n";
                    $data = '';

                    return -1;
                }
                $t = time();
            } else {
                if (false === $buff && !Helper::isIgnorableSocketErrors($this->socket)) {
                    $err = socket_strerror(socket_last_error($this->socket));
                    echo "{level:warning}Connection closed receiving content: {$err}\n";

                    return -1;
                }
                if ($data) {
                    // We started to get some data,
                    // we need to fully have it !
                    usleep(5000);
                    if ((time() - $t) > $this->timeout) {
                        // Timeout
                        echo "{level:warning}Timeout while waiting for remaining content.\n";

                        return -1;
                    }
                } else {
                    // We didnt start getting data, we can return
                    return 0;
                }
            }
        } while (!$len || "\0" != $data[$len - 1]);

        $data = trim($data);

        return $len;
    }

    /**
     * Returns the maximum size of a message in read().
     */
    protected function getMaxMessageSize(): int
    {
        return 1024 * 1024;
    }
}
