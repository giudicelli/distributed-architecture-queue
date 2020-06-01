<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers;

use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;

/**
 * This trait is used by all the local process implementation.
 *
 *  @author Frédéric Giudicelli
 */
trait LocalProcessTrait
{
    /**
     * Build a shell command to start a local queue process.
     *
     * @param string $command     the specific command to be interpreted by the Slave\HandlerQueue class
     * @param array  $extraParams some extra params to pass to the shell command
     *
     * @return string the shell command
     */
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
