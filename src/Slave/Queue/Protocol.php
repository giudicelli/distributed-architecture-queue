<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue;

use giudicelli\DistributedArchitecture\Slave\StoppableInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Exception\NetworkException;
use MustStopException;

class Protocol implements ProtocolInterface
{
    const END_COMMAND = "\0GDAQ_END\0";

    const END_COMMAND_LEN = 10;

    const COMMAND_HANDSHAKE = 'GDAQ_Handshake';

    const COMMAND_JOB = 'GDAQ_Job';

    const COMMAND_JOB_DONE = 'GDAQ_JobDone';

    public static $ignorableErrors = [
        self::ERROR_EINTR,
        self::ERROR_EAGAIN,
    ];

    /** @var StoppableInterface */
    protected $stoppable;

    protected $timeout;

    protected $maxLength;

    public function __construct(StoppableInterface $stoppable, int $timeout = 5, int $maxLength = 1048576)
    {
        $this->stoppable = $stoppable;
        $this->timeout = $timeout;
        $this->maxLength = $maxLength;
    }

    public function sendHandshake($socket): void
    {
        $this->send($socket, self::COMMAND_HANDSHAKE);
    }

    public function receiveHandshake($socket): bool
    {
        $data = $this->recv($socket);
        if (!$data) {
            return false;
        }
        if (self::COMMAND_HANDSHAKE !== $data) {
            throw new NetworkException('Invalid command while waiting for the handshake');
        }

        return true;
    }

    public function sendJob($socket, \JsonSerializable $job): void
    {
        $jobStr = json_encode($job);
        $this->send($socket, self::COMMAND_JOB.$jobStr);
    }

    public function receiveJob($socket): ?array
    {
        $data = $this->recv($socket);
        if (!$data) {
            return null;
        }
        $len = strlen(self::COMMAND_JOB);
        if (self::COMMAND_JOB !== substr($data, 0, $len)) {
            throw new NetworkException('Invalid command while waiting for a job');
        }

        // Remove command
        $data = substr($data, $len);

        // decode the json
        $data = json_decode($data, true);
        if (empty($data)) {
            throw new NetworkException('Invalid JSON while receiving a job');
        }

        return $data;
    }

    public function sendJobDone($socket): void
    {
        $this->send($socket, self::COMMAND_JOB_DONE);
    }

    public function receiveJobDone($socket): bool
    {
        $data = $this->recv($socket);
        if (!$data) {
            return false;
        }
        if (self::COMMAND_JOB_DONE !== $data) {
            throw new NetworkException('Invalid command while waiting for the job done');
        }

        return true;
    }

    public function isErrorIgnorable($socket = null): bool
    {
        if ($socket) {
            $errno = socket_last_error($socket);
        } else {
            $errno = socket_last_error();
        }
        if (!$errno) {
            return true;
        }

        return in_array($errno, self::$ignorableErrors);
    }

    public function setSocketOptions($socket): void
    {
        if (!@socket_set_nonblock($socket)) {
            $err = socket_strerror(socket_last_error($socket));

            throw new NetworkException("Failed to call socket_set_nonblock: {$err}");
        }

        $linger = ['l_linger' => 0, 'l_onoff' => 1];
        if (!@socket_set_option($socket, SOL_SOCKET, SO_LINGER, $linger)) {
            $err = socket_strerror(socket_last_error($socket));

            throw new NetworkException("Failed to call socket_set_option SO_LINGER: {$err}");
        }
        if (!@socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1)) {
            $err = socket_strerror(socket_last_error($socket));

            throw new NetworkException("Failed to call socket_set_option SO_KEEPALIVE: {$err}");
        }
        /*
        if (!@socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, intval($this->maxLength * 0.1))) {
            $err = socket_strerror(socket_last_error($socket));

            throw new NetworkException("Failed to call socket_set_option SO_RCVBUF: {$err}");
        }
        if (!@socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 8192)) {
            $err = socket_strerror(socket_last_error($socket));

            throw new NetworkException("Failed to call socket_set_option SO_SNDBUF: {$err}");
        }
        */
        // Will fail in case of a Unix socket, but it's ok
        @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
    }

    protected function recv($socket): ?string
    {
        $data = '';
        $dataLength = 0;
        $lastReceivedTime = 0;
        do {
            if ($this->stoppable->mustStop()) {
                throw new MustStopException();
            }

            $buff = '';
            $len = @socket_recv($socket, $buff, 8192, 0);

            if ($len) {
                $data .= $buff;
                $dataLength += $len;
                if ($dataLength > $this->maxLength) {
                    throw new NetworkException('Data is too long');
                }
                $lastReceivedTime = time();
            } else {
                if (false === $len && !$this->isErrorIgnorable($socket)) {
                    $err = socket_strerror(socket_last_error($socket));

                    throw new NetworkException("Connection closed while receiving content: {$err}");
                }
                if (!$dataLength) {
                    // We didn't start getting any data, we can return
                    return null;
                }

                // We started to have some data, we need to have it full
                // So let's wait a bit before retrying (10ms)
                usleep(10000);
                if ((time() - $lastReceivedTime) > $this->timeout) {
                    // Timeout
                    throw new NetworkException('Timeout while receiving data');
                }
            }
        } while (!$dataLength || self::END_COMMAND !== substr($data, -self::END_COMMAND_LEN));

        return substr($data, 0, -self::END_COMMAND_LEN);
    }

    protected function send($socket, string $data): void
    {
        if ('' === $data) {
            return;
        }

        $dataLength = strlen($data) + self::END_COMMAND_LEN;
        $data .= self::END_COMMAND;

        $bufferSize = 8192;
        $lastSentTime = time();

        do {
            $dataToSend = substr($data, 0, $bufferSize);
            $dataToSendLength = $dataLength < $bufferSize ? $dataLength : $bufferSize;

            for (;;) {
                if ($this->stoppable->mustStop()) {
                    throw new MustStopException();
                }
                $len = @socket_send($socket, $dataToSend, $dataToSendLength, 0);
                if (false === $len && !$this->isErrorIgnorable($socket)) {
                    $err = socket_strerror(socket_last_error($socket));

                    throw new NetworkException("Connection closed while sending content: {$err}");
                }
                if ($len) {
                    $lastSentTime = time();

                    break;
                }
                // Wait a bit before retrying (10ms)
                usleep(10000);

                if ((time() - $lastSentTime) > $this->timeout) {
                    // Timeout
                    throw new NetworkException('Timeout while sending data');
                }
            }
            $data = substr($data, $dataToSendLength);
            $dataLength -= $dataToSendLength;
        } while ($dataLength > 0);
    }
}
