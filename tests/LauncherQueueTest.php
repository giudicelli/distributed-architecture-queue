<?php

declare(strict_types=1);

namespace giudicelli\DistributedArchitectureQueue\tests;

use giudicelli\DistributedArchitecture\Master\Handlers\GroupConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Config as LocalConsumerConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote\Config as RemoteConsumerConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local\Config as LocalFeederConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Remote\Config as RemoteFeederConfig;
use giudicelli\DistributedArchitectureQueue\Master\LauncherQueue;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 * @coversNothing
 */
final class LauncherQueueTest extends TestCase
{
    private $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
    }

    /**
     * @before
     */
    public function resetLogger()
    {
        $this->logger->reset();
    }

    public function testLocalOneConsumer(): void
    {
        $groupConfig = $this->buildLocalGroupConfig('test', 'tests/SlaveFile.php');

        $master = new LauncherQueue(true, $this->logger);

        $master
            ->setMaxRunningTime(30)
            ->run([$groupConfig])
        ;

        $output = $this->logger->getOutput();
        sort($output);

        $expected = [
            'debug - [test] [localhost] [Consumer - tests/SlaveFile.php/2/2] Connected to /tmp/gdaq_117d4fd0db608747bc65ba337b9d0531c6836137.sock',
            'debug - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'debug - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'debug - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'info - [test] [localhost] [Consumer - tests/SlaveFile.php/2/2] MyType:1',
            'info - [test] [localhost] [Consumer - tests/SlaveFile.php/2/2] MyType:2',
            'info - [test] [localhost] [Consumer - tests/SlaveFile.php/2/2] MyType:3',
            'info - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Waiting for new connections',
            'notice - [master] Stopping...',
            'notice - [test] [localhost] [Consumer - tests/SlaveFile.php/2/2] Ended',
            'notice - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Ended',
            'notice - [test] [localhost] [Feeder - tests/SlaveFile.php/1/1] Feeder queue is empty',
        ];
        $this->assertEquals($expected, $output);
    }

    public function testRemoteOneConsumer(): void
    {
        $groupConfig = $this->buildRemoteGroupConfig('test', 'tests/SlaveFile.php');

        $master = new LauncherQueue(true, $this->logger);
        $master
            ->setMaxRunningTime(30)
            ->run([$groupConfig])
        ;

        $output = $this->logger->getOutput();
        sort($output);

        $expected = [
            'debug - [test] [127.0.0.2] Connected to host',
            'debug - [test] [127.0.0.2] Connected to host',
            'debug - [test] [127.0.0.2] Connected to host',
            'debug - [test] [127.0.0.2] Connected to host',
            'debug - [test] [127.0.0.2] [Consumer - tests/SlaveFile.php/2/2] Connected to /tmp/gdaq_117d4fd0db608747bc65ba337b9d0531c6836137.sock',
            'debug - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'debug - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'debug - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Available consumers: 1 / 1',
            'info - [test] [127.0.0.2] [Consumer - tests/SlaveFile.php/2/2] MyType:1',
            'info - [test] [127.0.0.2] [Consumer - tests/SlaveFile.php/2/2] MyType:2',
            'info - [test] [127.0.0.2] [Consumer - tests/SlaveFile.php/2/2] MyType:3',
            'info - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Waiting for new connections',
            'notice - [master] Stopping...',
            'notice - [test] [127.0.0.2] Ended',
            'notice - [test] [127.0.0.2] Ended',
            'notice - [test] [127.0.0.2] [Consumer - tests/SlaveFile.php/2/2] Ended',
            'notice - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Ended',
            'notice - [test] [127.0.0.2] [Feeder - tests/SlaveFile.php/1/1] Feeder queue is empty',
            'notice - [test] [127.0.0.2] [master] Received SIGTERM, stopping',
            'notice - [test] [127.0.0.2] [master] Received SIGTERM, stopping',
            'notice - [test] [127.0.0.2] [master] Stopping...',
            'notice - [test] [127.0.0.2] [master] Stopping...',
        ];
        $this->assertEquals($expected, $output);
    }

    private function buildLocalGroupConfig(string $name, string $command, $count = 1): GroupConfig
    {
        $groupConfig = new GroupConfig();
        $groupConfig->setName($name);
        $groupConfig->setCommand($command);

        $processConfigs = [
            (new LocalFeederConfig())
                ->setBindTo('127.0.0.2')
                ->setPort(9999),
        ];
        for ($i = 0; $i < $count; ++$i) {
            $processConfigs[] = (new LocalConsumerConfig())
                ->setHost('127.0.0.2')
                ->setPort(9999)
            ;
        }
        $groupConfig->setProcessConfigs($processConfigs);

        return $groupConfig;
    }

    private function buildRemoteGroupConfig(string $name, string $command, $count = 1): GroupConfig
    {
        $groupConfig = new GroupConfig();
        $groupConfig->setName($name);
        $groupConfig->setCommand($command);

        $processConfigs = [
            (new RemoteFeederConfig())
                ->setBindTo('127.0.0.2')
                ->setPort(9999)
                ->setHosts(['127.0.0.2']),
        ];
        for ($i = 0; $i < $count; ++$i) {
            $processConfigs[] = (new RemoteConsumerConfig())
                ->setHost('127.0.0.2')
                ->setPort(9999)
                ->setHosts(['127.0.0.2'])
            ;
        }
        $groupConfig->setProcessConfigs($processConfigs);

        return $groupConfig;
    }
}

class Logger extends AbstractLogger
{
    private $output = [];

    public function reset()
    {
        $this->output = [];
    }

    public function log($level, $message, array $context = [])
    {
        foreach ($context as $key => $value) {
            $message = str_replace('{'.$key.'}', $value, $message);
        }
        $this->output[] = "{$level} - {$message}";
    }

    public function getOutput(): array
    {
        return $this->output;
    }
}
