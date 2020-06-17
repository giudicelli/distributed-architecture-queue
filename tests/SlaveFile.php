<?php

namespace giudicelli\DistributedArchitectureQueue\tests;

include 'vendor/autoload.php';

use giudicelli\DistributedArchitecture\Slave\HandlerInterface;
use giudicelli\DistributedArchitectureQueue\Slave\HandlerQueue;
use giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder\FeederInterface;

if (empty($_SERVER['argv'][1])) {
    echo "Empty params\n";
    die();
}

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
        if (empty($this->items)) {
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
$handler->runQueue(function (HandlerInterface $handler, array $item) {
    echo $item['type'].':'.$item['id']."\n";
}, new Feeder());
