<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local;

use giudicelli\DistributedArchitecture\Config\ProcessConfigInterface;
use giudicelli\DistributedArchitecture\Master\Handlers\Local\Config as LocalConfig;
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function setPort(int $port): ConfigInterface
    {
        $this->port = $port;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function setBindTo(string $bindTo): ConfigInterface
    {
        $this->bindTo = $bindTo;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindTo(): string
    {
        return $this->bindTo;
    }

    /**
     * {@inheritdoc}
     */
    public function setInstancesCount(int $instancesCount): ProcessConfigInterface
    {
        if ($instancesCount > 1) {
            throw new \InvalidArgumentException('You cannot set the number of instances on a feeder');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstancesCount(): int
    {
        return 1;
    }
}
