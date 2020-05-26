<?php

namespace giudicelli\DistributedArchitectureQueue\Slave\Queue\Feeder;

interface FeederInterface
{
    /**
     * Return true if the feeder has no item.
     */
    public function empty(): bool;

    /**
     * Get one item to send to a consumer.
     */
    public function get(): ?\JsonSerializable;

    /**
     * There was an error handling an item.
     *
     * @param \JsonSerializable $item The item in error
     */
    public function error(\JsonSerializable $item): void;

    /**
     * An item was successfully handled.
     *
     * @param \JsonSerializable $item The item successfully handled
     */
    public function success(\JsonSerializable $item): void;
}
