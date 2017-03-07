<?php

namespace React\Stream;

class ThroughStream extends CompositeStream
{
    private $paused = false;

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

    public function pause()
    {
        parent::pause();
        $this->paused = true;
    }

    public function resume()
    {
        parent::resume();
        $this->paused = false;
    }

    public function write($data)
    {
        if (!$this->writable->isWritable()) {
            return false;
        }

        $this->readable->emit('data', array($this->filter($data)));

        return $this->writable->isWritable() && !$this->paused;
    }

    public function end($data = null)
    {
        if (!$this->writable->isWritable()) {
            return;
        }

        if (null !== $data) {
            $this->readable->emit('data', array($this->filter($data)));
        }

        $this->readable->emit('end');

        $this->writable->end();
    }
}
