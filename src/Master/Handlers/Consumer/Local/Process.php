<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local;

use giudicelli\DistributedArchitecture\Master\Handlers\Local\Process as LocalProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\ProcessTrait;
use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;

/**
 * A queue consumer process started on the same computer as the master.
 *
 * @author Frédéric Giudicelli
 *
 * @internal
 */
class Process extends LocalProcess
{
    use ProcessTrait;

    public static function getConfigClass(): string
    {
        return Config::class;
    }

    protected function buildShellCommand(): string
    {
        return $this->buildShellQueueCommand(HandlerQueue::PARAM_COMMAND_START_CONSUMER);
    }

    protected function getDisplay(): string
    {
        return 'Consumer - '.parent::getDisplay();
    }
}
