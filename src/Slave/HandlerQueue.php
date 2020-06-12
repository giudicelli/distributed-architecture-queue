<?php

namespace giudicelli\DistributedArchitectureQueue\Slave;

use giudicelli\DistributedArchitecture\Config\ConfigInterface;
use giudicelli\DistributedArchitecture\Config\ProcessConfigInterface;
use giudicelli\DistributedArchitecture\Helper\InterProcessLogger;
use giudicelli\DistributedArchitecture\Slave\Handler;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\ConfigInterface as ConsumerConfigInterface;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\ConfigInterface as FeederConfigInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Consumer\Client;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\FeederInterface;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\Server;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Protocol;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\ProtocolInterface;

/**
 * {@inheritdoc}
 *
 *  @author Frédéric Giudicelli
 */
class HandlerQueue extends Handler
{
    const PARAM_COMMAND_START_FEEDER = 'startFeeder';
    const PARAM_COMMAND_START_CONSUMER = 'startConsumer';

    /**
     * {@inheritdoc}
     */
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

    /**
     * Handle a queue command sent by the master.
     */
    protected function handleQueueCommand(callable $processCallback, FeederInterface $feeder): void
    {
        switch ($this->params[self::PARAM_COMMAND]) {
            case self::PARAM_COMMAND_START_FEEDER:
                $this->setUpSignalHandler();
                $processConfig = $this->getCommandConfigObject();
                $this->handleFeeder($feeder, $processConfig);

            break;
            case self::PARAM_COMMAND_START_CONSUMER:
                $this->setUpSignalHandler();
                $processConfig = $this->getCommandConfigObject();
                $this->handleConsumer($processCallback, $processConfig);

            break;
            default:
                $this->handleCommand();
        }
    }

    /**
     * Setup the signal handler to catch SIGTERM.
     */
    protected function setUpSignalHandler(): void
    {
        // We want to know when we're asked to stop
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, [&$this, 'signalHandler']);
    }

    /**
     * Start the feeder server.
     */
    protected function handleFeeder(FeederInterface $feeder, ProcessConfigInterface $config): void
    {
        if (!($config instanceof FeederConfigInterface)) {
            throw new \InvalidArgumentException('Unknown configuration class');
        }
        $port = $config->getPort();
        $bindTo = $config->getBindTo();

        $logger = new InterProcessLogger(false);

        $server = new Server(
            $logger,
            $this,
            $this->getProtocolHandler($config),
            $this->groupConfig->getName(),
            $bindTo,
            $port,
            $this->getTimeout($config, $this->groupConfig)
        );
        $server->run($feeder);
        $this->sendEnded();
    }

    /**
     * Start the consumer client.
     */
    protected function handleConsumer(callable $processCallback, ProcessConfigInterface $config): void
    {
        if (!($config instanceof ConsumerConfigInterface)) {
            throw new \InvalidArgumentException('Unknown configuration class');
        }
        $port = $config->getPort();
        $host = $config->getHost();

        $logger = new InterProcessLogger(false);

        $client = new Client(
            $logger,
            $this,
            $this->getProtocolHandler($config),
            $this->groupConfig->getName(),
            $host,
            $port,
            $this->getTimeout($config, $this->groupConfig)
        );

        $me = $this;
        $client->run(function (array $item) use ($me, $processCallback, $logger) {
            call_user_func($processCallback, $me, $item, $logger);
        });
        $this->sendEnded();
    }

    /**
     * Return a protocol handler, it has to be the same implementation between the feeder and the consumers.
     *
     * @param ConfigInterface $config A config interface to use its timeout
     *
     * @return ProtocolInterface an instance of ProtocolInterface
     */
    protected function getProtocolHandler(ConfigInterface $config): ProtocolInterface
    {
        return new Protocol($this, $this->getTimeout($config, $this->groupConfig));
    }

    protected function getTimeout(ConfigInterface $primary, ConfigInterface $secondary, int $default = 5): int
    {
        if ($primary->getTimeout() > 0) {
            return $primary->getTimeout();
        }
        if ($secondary->getTimeout() > 0) {
            return $secondary->getTimeout();
        }

        return $default;
    }
}
