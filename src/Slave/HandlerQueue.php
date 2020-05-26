<?php

namespace giudicelli\DistributedArchitectureQueue\Slave;

use giudicelli\DistributedArchitecture\Master\ProcessConfigInterface;
use giudicelli\DistributedArchitecture\Slave\Handler;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\ConfigInterface as ConsumerConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\ConfigInterface as FeederConfigInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Consumer\Client;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\FeederInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\Server;

class HandlerQueue extends Handler
{
    const PARAM_COMMAND_START_FEEDER = 'startFeeder';
    const PARAM_COMMAND_START_CONSUMER = 'startConsumer';

    public function run(callable $processCallback): void
    {
        throw new \InvalidArgumentException('You cannot use this method, please use runQueue');
    }

    /**
     * Run this queue handler.
     *
     * @param callable        $processCallback It will only be used if the started script is a consumer and not a feeder
     * @param FeederInterface $feeder          It will only be used if the started script is a feeder and not a consumer
     */
    public function runQueue(callable $processCallback, FeederInterface $feeder): void
    {
        if (!$this->isCommand()) {
            throw new \InvalidArgumentException('Expected '.self::PARAM_COMMAND.' in params');
        }
        $this->handleQueueCommand($processCallback, $feeder);
    }

    protected function handleQueueCommand(callable $processCallback, FeederInterface $feeder): void
    {
        $processConfig = $this->getCommandConfigObject();

        switch ($this->params[self::PARAM_COMMAND]) {
            case self::PARAM_COMMAND_START_FEEDER:
                $this->setUpSignalHandler();
                $this->handleFeeder($feeder, $processConfig);

            break;
            case self::PARAM_COMMAND_START_CONSUMER:
                $this->setUpSignalHandler();
                $this->handleConsumer($processCallback, $processConfig);

            break;
            default:
                $this->handleCommand();
        }
    }

    protected function setUpSignalHandler(): void
    {
        // We want to know when we're asked to stop
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [&$this, 'signalHandler']);
    }

    protected function handleFeeder(FeederInterface $feeder, ProcessConfigInterface $config): void
    {
        if (!($config instanceof FeederConfigInterface)) {
            throw new \InvalidArgumentException('Unknown configuration class');
        }
        $port = $config->getPort();
        $bindTo = $config->getBindTo();

        $server = new Server($this, $this->groupConfig->getName(), $bindTo, $port);
        $server->run($feeder);
        $this->sendEnded();
    }

    protected function handleConsumer(callable $processCallback, ProcessConfigInterface $config): void
    {
        if (!($config instanceof ConsumerConfigInterface)) {
            throw new \InvalidArgumentException('Unknown configuration class');
        }
        $port = $config->getPort();
        $host = $config->getHost();

        $client = new Client($this, $this->groupConfig->getName(), $host, $port);

        $me = $this;
        $client->run(function (array $item) use ($me, $processCallback) {
            call_user_func($processCallback, $me, $item);
        });
        $this->sendEnded();
    }
}
