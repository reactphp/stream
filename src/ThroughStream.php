<?php

namespace React\Stream;

use React\Promise\FulfilledPromise;

class ThroughStream extends CompositeStream
{
    public function __construct()
    {
        $readable = new ReadableStream();
        $writable = new WritableStream();

        parent::__construct($readable, $writable);
    }

    public function filter($data)
    {
        return $data;
    }

    public function write($data)
    {
        $this->readable->emit('data', array($this->filter($data), $this));
        return new FulfilledPromise();
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->readable->emit('data', array($this->filter($data), $this));
        }

        $this->writable->end($data);
    }
}
