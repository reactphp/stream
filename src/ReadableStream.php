<?php

namespace React\Stream;

use Evenement\EventEmitter;

class ReadableStream extends EventEmitter implements ReadableStreamInterface
{
    protected $closed = false;

    public function isReadable()
    {
        return !$this->closed;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close');
        $this->removeAllListeners();
    }
}
