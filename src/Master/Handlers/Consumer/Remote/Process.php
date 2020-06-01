<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote;

use giudicelli\DistributedArchitecture\Master\Handlers\Remote\Process as RemoteProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Config as ConfigLocal;

/**
 * A queue consumer process started on a remote host.
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
