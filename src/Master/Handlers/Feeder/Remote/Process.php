<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Remote;

use giudicelli\DistributedArchitecture\Master\Handlers\Remote\Process as RemoteProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local\Config as ConfigLocal;

/**
 * A queue feeder process started on a remote host.
 *
 * @author Frédéric Giudicelli
 *
 * @internal
 */
class Process extends RemoteProcess
{
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
    protected function getRemoteConfigClass(): string
    {
        return ConfigLocal::class;
    }
}
