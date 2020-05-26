<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local;

use giudicelli\DistributedArchitecture\Master\Handlers\Local\Process as LocalProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\ProcessTrait;
use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;

/**
 * A queue feeder process started on the same computer as the master.
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

    protected function getDisplay(): string
    {
        return 'Feeder - '.parent::getDisplay();
    }

    protected function buildShellCommand(): string
    {
        return $this->buildShellQueueCommand(HandlerQueue::PARAM_COMMAND_START_FEEDER);
    }
}
