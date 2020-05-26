<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local;

use giudicelli\DistributedArchitecture\Master\Handlers\Local\Config as LocalConfig;
use giudicelli\DistributedArchitecture\Master\ProcessConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\ConfigInterface;

/**
 * The config to start a local queue feeder process.
 *
 * @author Frédéric Giudicelli
 */
class Config extends LocalConfig implements ConfigInterface
{
    protected $port = 9999;

    protected $bindTo = 'localhost';

    public function fromArray(array $config): void
    {
        parent::fromArray($config);

        if (!empty($config['port'])) {
            $this->setPort($config['port']);
        }
        if (!empty($config['bindTo'])) {
            $this->setBindTo($config['bindTo']);
        }
    }

    public function setPort(int $port): ConfigInterface
    {
        $this->port = $port;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setBindTo(string $bindTo): ConfigInterface
    {
        $this->bindTo = $bindTo;

        return $this;
    }

    public function getBindTo(): string
    {
        return $this->bindTo;
    }

    public function setInstancesCount(int $instancesCount): ProcessConfigInterface
    {
        if ($instancesCount > 1) {
            throw new \InvalidArgumentException('You cannot set the number of instances on a feeder');
        }

        return $this;
    }

    public function getInstancesCount(): int
    {
        return 1;
    }
}
