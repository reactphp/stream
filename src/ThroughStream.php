<?php

namespace React\Stream;

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
        if (!$this->writable) {
            return false;
        }

        $this->readable->emit('data', array($this->filter($data)));

        return true;
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->readable->emit('data', array($this->filter($data)));
        }

        $this->readable->emit('end');

        $this->writable->end();
    }
}
