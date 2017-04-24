<?php

namespace React\Stream;

use Evenement\EventEmitter;

class ThroughStream extends EventEmitter implements DuplexStreamInterface
{
    private $readable = true;
    private $writable = true;
    private $closed = false;
    private $paused = false;
    private $drain = false;

    public function filter($data)
    {
        return $data;
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        if ($this->drain) {
            $this->drain = false;
            $this->emit('drain');
        }
        $this->paused = false;
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function write($data)
    {
        if (!$this->writable) {
            return false;
        }

        $this->emit('data', array($this->filter($data)));

        if ($this->paused) {
            $this->drain = true;
            return false;
        }

        return true;
    }

    public function end($data = null)
    {
        if (!$this->writable) {
            return;
        }

        if (null !== $data) {
            $this->write($data);
        }

        $this->readable = false;
        $this->writable = false;
        $this->paused = true;
        $this->drain = false;

        $this->emit('end');
        $this->close();
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->readable = false;
        $this->writable = false;
        $this->closed = true;
        $this->paused = true;
        $this->drain = false;

        $this->emit('close');
    }
}
