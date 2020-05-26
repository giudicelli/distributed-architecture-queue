<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers;

use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;

trait ProcessTrait
{
    protected function buildShellQueueCommand(string $command, ?array $extraParams = null): string
    {
        if ($this->config->getBinPath()) {
            $bin = $this->config->getBinPath();
        } elseif ($this->groupConfig->getBinPath()) {
            $bin = $this->groupConfig->getBinPath();
        } else {
            $bin = PHP_BINARY;
        }

        $params = $this->buildParams();
        $params[HandlerQueue::PARAM_COMMAND] = $command;
        $params[HandlerQueue::PARAM_CONFIG] = $this->config;
        $params[HandlerQueue::PARAM_CONFIG_CLASS] = get_class($this->config);
        if ($extraParams) {
            $params = array_merge($params, $extraParams);
        }

        $params = escapeshellarg(json_encode($params));

        return $bin.' '.$this->groupConfig->getCommand().' '.$params;
    }
}
