<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers;

use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;

trait ProcessTrait
{
    protected function buildShellQueueCommand(string $command, ?array $extraParams = null): string
    {
        $params = $this->buildParams();
        $params[HandlerQueue::PARAM_COMMAND] = $command;
        $params[HandlerQueue::PARAM_CONFIG] = $this->config;
        $params[HandlerQueue::PARAM_CONFIG_CLASS] = get_class($this->config);
        if ($extraParams) {
            $params = array_merge($params, $extraParams);
        }

        return $this->getShellCommand($params);
    }
}
