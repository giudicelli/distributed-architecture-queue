<?php

namespace giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote;

use giudicelli\DistributedArchitecture\Master\Handlers\Remote\Process as RemoteProcess;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Config as ConfigLocal;
use giudicelli\DistributedArchitectureQueue\Master\LauncherQueue;

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

    protected function getRemoteLauncherClass(): string
    {
        return LauncherQueue::class;
    }
}
