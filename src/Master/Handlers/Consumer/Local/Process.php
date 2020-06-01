<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local;

use giudicelli\DistributedArchitecture\Master\Handlers\Local\Process as LocalProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\LocalProcessTrait;
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
    use LocalProcessTrait;

    /**
     * {@inheritdoc}
     */
    public static function getConfigClass(): string
    {
        return Config::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplay(): string
    {
        return 'Consumer - '.parent::getDisplay();
    }

    /**
     * {@inheritdoc}
     */
    protected function buildShellCommand(): string
    {
        return $this->buildShellQueueCommand(HandlerQueue::PARAM_COMMAND_START_CONSUMER);
    }
}
