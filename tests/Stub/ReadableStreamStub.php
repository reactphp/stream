<?php

namespace React\Tests\Stream\Stub;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class ReadableStreamStub extends EventEmitter implements ReadableStreamInterface
{
    public $readable = true;
    public $paused = false;

    public function isReadable()
    {
        return true;
    }

    // trigger data event
    public function write($data)
    {
        $this->emit('data', [$data]);
    }

    // trigger error event
    public function error($error)
    {
        $this->emit('error', [$error]);
    }

    // trigger end event
    public function end()
    {
        $this->emit('end', []);
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        $this->paused = false;
    }

    public function close()
    {
        $this->readable = false;

        $this->emit('close');
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
