<?php

namespace giudicelli\DistributedArchitectureQueue\Master;

use giudicelli\DistributedArchitecture\Master\EventsInterface;
use giudicelli\DistributedArchitecture\Master\GroupConfigInterface;
use giudicelli\DistributedArchitecture\Master\Launcher;
use giudicelli\DistributedArchitecture\Master\ProcessConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\ConfigInterface as ConsumerConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Process as ProcessConsumerLocal;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote\Process as ProcessConsumerRemote;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\ConfigInterface as FeederConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local\Process as ProcessFeederLocal;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Remote\Process as ProcessFeederRemote;

/**
 * This class is the implementation of the LauncherInterface interface. Its main role is to launch feeder/consumers processes.
 *
 *  @author Frédéric Giudicelli
 */
class LauncherQueue extends Launcher
{
    /**
     * {@inheritdoc}
     */
    protected function checkGroupConfigs(array $groupConfigs): void
    {
        parent::checkGroupConfigs($groupConfigs);

        foreach ($groupConfigs as $groupConfig) {
            // Make sure we have a single feeder in each group configuration,
            // and at least one consumer
            $processConfigs = $groupConfig->getProcessConfigs();
            $feeder = null;
            $consumers = [];
            foreach ($processConfigs as $processConfig) {
                if (in_array(FeederConfigInterface::class, class_implements($processConfig))) {
                    if ($feeder) {
                        throw new \InvalidArgumentException($groupConfig->getName().': You can have only one feeder');
                    }
                    $feeder = $processConfig;
                } elseif (in_array(ConsumerConfigInterface::class, class_implements($processConfig))) {
                    $consumers[] = $processConfig;
                }
            }
            if (!$feeder) {
                throw new \InvalidArgumentException($groupConfig->getName().': You must have one feeder');
            }
            if (empty($consumers)) {
                throw new \InvalidArgumentException($groupConfig->getName().': You must have at least one consumer');
            }

            // Make sure the feeder is the first process
            $groupConfig->setProcessConfigs(array_merge([$feeder], $consumers));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function startGroupProcess(GroupConfigInterface $groupConfig, ProcessConfigInterface $processConfig, int $idStart, int $groupIdStart, int $groupCount, ?EventsInterface $events): int
    {
        $count = parent::startGroupProcess($groupConfig, $processConfig, $idStart, $groupIdStart, $groupCount, $events);
        if (in_array(FeederConfigInterface::class, class_implements($processConfig))) {
            // Give a bit of time for the feeder to start
            sleep(2);
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
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
