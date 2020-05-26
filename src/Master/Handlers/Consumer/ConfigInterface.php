<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer;

use giudicelli\DistributedArchitecture\Master\ProcessConfigInterface;

interface ConfigInterface extends ProcessConfigInterface
{
    /**
     * Set the feeder's port.
     */
    public function setPort(int $port): self;

    /**
     * Returns the feeder's port.
     */
    public function getPort(): int;

    /**
     * Set the feeder's host.
     */
    public function setHost(string $host): self;

    /**
     * Returns the feeder's host.
     */
    public function getHost(): string;
}
