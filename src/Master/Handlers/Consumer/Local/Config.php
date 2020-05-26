<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local;

use giudicelli\DistributedArchitecture\Master\Handlers\Local\Config as LocalConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\ConfigInterface;

/**
 * The config to start a local queue consumer process.
 *
 * @author Frédéric Giudicelli
 */
class Config extends LocalConfig implements ConfigInterface
{
    protected $port = 9999;

    protected $host = 'localhost';

    public function fromArray(array $config): void
    {
        parent::fromArray($config);

        if (!empty($config['port'])) {
            $this->setPort($config['port']);
        }
        if (!empty($config['host'])) {
            $this->setHost($config['host']);
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

    public function setHost(string $host): ConfigInterface
    {
        $this->host = $host;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
