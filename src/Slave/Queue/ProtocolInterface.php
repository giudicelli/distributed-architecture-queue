<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue;

interface ProtocolInterface
{
    const ERROR_EINTR = 4;
    const ERROR_EAGAIN = 11;

    /**
     * Send the handshake.
     *
     * @param resource $socket the socket
     */
    public function sendHandshake($socket): void;

    /**
     * Receive the handshake.
     *
     * @param resource $socket the socket
     *
     * @return bool if the handshake was read
     */
    public function receiveHandshake($socket): bool;

    /**
     * Send a job.
     *
     * @param resource          $socket the socket
     * @param \JsonSerializable $job    the job to send
     */
    public function sendJob($socket, \JsonSerializable $job): void;

    /**
     * Receive a job.
     *
     * @param resource $socket the socket
     *
     * @return array null if no job was received
     */
    public function receiveJob($socket): ?array;

    /**
     * Send job done.
     *
     * @param resource $socket the socket
     */
    public function sendJobDone($socket): void;

    /**
     * Receive job done.
     *
     * @param resource $socket the socket
     *
     * @return bool if job done was read
     */
    public function receiveJobDone($socket): bool;

    /**
     * Test if last socket error is ignorable.
     *
     * @param resource $socket Optional socket
     *
     * @return bool true if ignorable, else false
     */
    public function isErrorIgnorable($socket = null): bool;

    /**
     * Set a socket standard's options.
     *
     * @param resource $socket the socket to set
     */
    public function setSocketOptions($socket): void;
}
