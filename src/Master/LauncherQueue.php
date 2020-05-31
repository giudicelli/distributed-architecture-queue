<?php

namespace giudicelli\DistributedArchitectureQueue\Master;

use giudicelli\DistributedArchitecture\Master\EventsInterface;
use giudicelli\DistributedArchitecture\Master\GroupConfigInterface;
use giudicelli\DistributedArchitecture\Master\Launcher;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Process as ProcessConsumerLocal;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote\Process as ProcessConsumerRemote;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\ConfigInterface as FeederConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local\Process as ProcessFeederLocal;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Remote\Process as ProcessFeederRemote;

class LauncherQueue extends Launcher
{
    /**
     * {@inheritdoc}
     */
    public function run(array $groupConfigs, ?EventsInterface $events = null): void
    {
        foreach ($groupConfigs as $groupConfig) {
            // Make sure we have a single feeder in the configuration
            $processConfigs = $groupConfig->getProcessConfigs();
            $feeder = null;
            foreach ($processConfigs as $processConfig) {
                if (in_array(FeederConfigInterface::class, class_implements($processConfig))) {
                    if ($feeder) {
                        throw new \InvalidArgumentException($groupConfig->getName().': You can have only one feeder');
                    }
                    $feeder = $processConfig;
                }
            }
            if (!$feeder) {
                throw new \InvalidArgumentException($groupConfig->getName().': You must have one feeder');
            }
        }

        parent::run($groupConfigs, $events);
    }

    /**
     * {@inheritdoc}
     */
    protected function startGroup(GroupConfigInterface $groupConfig, int $idStart, int $groupIdStart, int $groupCount, ?EventsInterface $events): int
    {
        $processesCount = 0;

        // First we always start the feeder process
        foreach ($groupConfig->getProcessConfigs() as $processConfig) {
            if ($this->mustStop) {
                break;
            }
            if (in_array(FeederConfigInterface::class, class_implements($processConfig))) {
                $count = $this->startGroupProcess($groupConfig, $processConfig, $idStart, $groupIdStart, $groupCount, $events);

                $idStart += $count;
                $groupIdStart += $count;
                $processesCount += $count;

                break;
            }
        }

        // Give a bit of time to the feeder to startup
        if (!$this->mustStop) {
            sleep(2);
        }

        // Now we start the consumer processes
        foreach ($groupConfig->getProcessConfigs() as $processConfig) {
            if ($this->mustStop) {
                break;
            }
            if (!in_array(FeederConfigInterface::class, class_implements($processConfig))) {
                $count = $this->startGroupProcess($groupConfig, $processConfig, $idStart, $groupIdStart, $groupCount, $events);

                $idStart += $count;
                $groupIdStart += $count;
                $processesCount += $count;

                break;
            }
        }

        return $processesCount;
    }

    protected function getProcessHandlersList(): array
    {
        return [
            ProcessFeederRemote::class,
            ProcessFeederLocal::class,
            ProcessConsumerRemote::class,
            ProcessConsumerLocal::class,
        ];
    }
}
