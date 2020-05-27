<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;

abstract class AbstractNetwork
{
    protected $socket;
    protected $socketUnixPath = '';

    protected $port = 0;

    /** @var StoppableInterface */
    protected $stoppable;

    /** @var ProtocolInterface */
    protected $protocol;

    protected $id = '';
    protected $timeout = 5;

    public function __construct(StoppableInterface $stoppable, ProtocolInterface $protocol, string $id, int $port, $timeout = 5)
    {
        $this->stoppable = $stoppable;
        $this->protocol = $protocol;
        $this->id = $id;
        $this->timeout = $timeout;
        $this->port = $port;
        $this->socketUnixPath = '/tmp/gdaq_'.sha1($id.'-'.$port).'.sock';
    }

    public function __destruct()
    {
        $this->clean();
    }

    public function clean(): void
    {
        if ($this->socket) {
            @socket_close($this->socket);
            $this->socket = null;
        }
    }
}
