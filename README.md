
# distributed-architecture-queue ![CI](https://github.com/giudicelli/distributed-architecture-queue/workflows/CI/badge.svg)

PHP Distributed Architecture Queue is a library that extends [distributed-architecture](https://github.com/giudicelli/distributed-architecture). It implements a feeder/consumers system to allow easy and quick usage in a distributed architecture.

## Installation

```bash
$ composer require giudicelli/distributed-architecture-queue
```

## Using

To run your distributed architecture queue you will mainly need to use two classes Master\LauncherQueue and Slave\HandlerQueue.

### Master process

Here is a simple example to start the master process.

```php
use giudicelli\DistributedArchitecture\Master\Handlers\GroupConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Local\Config as LocalConsumerConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Consumer\Remote\Config as RemoteConsumerConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Local\Config as LocalFeederConfig;
use giudicelli\DistributedArchitectureQueue\Master\Handlers\Feeder\Remote\Config as RemoteFeederConfig;
use giudicelli\DistributedArchitectureQueue\Master\LauncherQueue;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    public function log($level, $message, array $context = [])
    {
        foreach ($context as $key => $value) {
            $message = str_replace('{'.$key.'}', $value, $message);
        }
        echo "{$level} - {$message}\n";
        flush();
    }
}

$logger = new Logger();

$groupConfigs = [
    (new GroupConfig())
        ->setName('First Group')
        ->setCommand('script.php')
        ->setProcessConfigs([
            (new LocalFeederConfig())
                ->setBindTo('192.168.0.1')
                ->setPort(9999),
            (new RemoteConsumerConfig())
                ->setHost('192.168.0.1')
                ->setPort(9999)
                ->setHosts(['remote-server1', 'remote-server2'])
                ->setInstancesCount(3),
        ]),
];

(new LauncherQueue($logger))
    ->setMaxRunningTime(3600)
    ->run($groupConfigs);
```

The above code creates one group called "First Group" and it will run "script.php" :
- 1 feeder instance launched on the local machine, it will listen on 192.168.0.1:9999,
- 3 consumer instances on the "remote-server1" machine,
- 3 consumer instances on the "remote-server2" machine.

All 6 consumer instances will connect to the feeder instance listening on 192.168.0.1:9999.

The "Master\LauncherQueue" instance will run for 1 hour before it stops all instances and returns. it's usually a good idea to restart the master after a certain time, to start a new clean environment.

Keep in mind that a "Master\LauncherQueue" instance will run forever, unless you kill it using a SIGTERM.

### Slave process

A slave process must use the "Slave\HandlerQueue" class, as the master will be sending commands that need to handled. It also allows you're script to do a clean exit upon the master's request. A single script can perform both type of tasks, being a feeder or a consumer.

Using the above example, here is an possible implementation for "script.php".

```php
use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\FeederInterface;

if (empty($_SERVER['argv'][1])) {
    echo "Empty params\n";
    die();
}
/**
 * The is an example of a serializable job implementation.
 */
class Job implements \JsonSerializable
{
    public $id = 0;
    public $type = '';

    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
        ];
    }
}

/**
 * The is an example of a feeder queue implementation. It's returns the jobs that will be sent to the consumers.
 */
class Feeder implements FeederInterface
{
    private $items = [];
    private $successes = [];
    private $errors = [];

    public function __construct()
    {
        $item = new Job();
        $item->id = 1;
        $item->type = 'MyType';
        $this->items[] = $item;

        $item = new Job();
        $item->id = 2;
        $item->type = 'MyType';
        $this->items[] = $item;

        $item = new Job();
        $item->id = 3;
        $item->type = 'MyType';
        $this->items[] = $item;
    }

    public function empty(): bool
    {
        return empty($this->items);
    }

    public function get(): ?\JsonSerializable
    {
        if ($this->empty()) {
            return null;
        }

        $item = $this->items[0];
        array_splice($this->items, 0, 1);

        return $item;
    }

    public function success(\JsonSerializable $item): void
    {
        $this->successes[] = $item;
    }

    public function error(\JsonSerializable $item): void
    {
        $this->errors[] = $item;
    }
}

$handler = new HandlerQueue($_SERVER['argv'][1]);
$handler->runQueue(
    // The consumer callback
    function (HandlerQueue $handler, array $item, LoggerInterface $logger) {

        // Anything echoed here will be considered log level "info" by the master process.
        // If you want another level for certain messages, use $logger.
        // echo "Hello world!\n" is the same as $logger->info('Hello world!')


        // I received a job to handle, the job is an array form of the Job class.
        echo $item['type'].':'.$item['id']."\n";
    },
    // The feeder accesses the jobs queue
    new Feeder()
);

```