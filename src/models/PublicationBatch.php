<?php

namespace craftpulse\cockpit\models;

use craft\base\Batchable;

class PublicationBatch implements Batchable
{
    public function __construct(public readonly array $items) {

    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getSlice(int $offset, int $limit): iterable
    {
        return array_slice($this->items, $offset, $limit);
    }

    // This is required for full Batchable interface
    public function getItemAt(int $index): mixed
    {
        return $this->items[$index] ?? null;
    }

    // Some batch implementations expect this method
    public function getTotalItems(): int
    {
        return $this->count();
    }
}
