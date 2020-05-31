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
    public static function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getRemoteConfigClass(): string
    {
        return ConfigLocal::class;
    }
}
