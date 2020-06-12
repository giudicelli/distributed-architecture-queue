<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder;

use giudicelli\DistributedArchitecture\Config\ProcessConfigInterface;

/**
 * Define the interface for a feeder config.
 *
 * @author Frédéric Giudicelli
 */
interface ConfigInterface extends ProcessConfigInterface
{
    /**
     * Set the port the feeder must listen on.
     */
    public function setPort(int $port): self;

    /**
     * Returns the port the feeder must listen on.
     */
    public function getPort(): int;

    /**
     * Set the IP the feeder must bind to.
     */
    public function setBindTo(string $bindTo): self;

    /**
     * Returns the IP the feeder must bind to.
     */
    public function getBindTo(): string;
}
