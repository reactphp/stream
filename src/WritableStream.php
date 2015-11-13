<?php

namespace React\Stream;

use Evenement\EventEmitter;

class WritableStream extends EventEmitter implements WritableStreamInterface
{
    protected $closed = false;

    public function write($data)
    {
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->close();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit(Event::END, array($this));
        $this->emit(Event::CLOSE, array($this));
        $this->removeAllListeners();
    }
}
