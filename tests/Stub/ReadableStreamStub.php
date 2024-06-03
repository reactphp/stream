<?php

namespace React\Tests\Stream\Stub;

use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

class ReadableStreamStub extends EventEmitter implements ReadableStreamInterface
{
    /** @var bool */
    public $readable = true;

    /** @var bool */
    public $paused = false;

    public function isReadable(): bool
    {
        return true;
    }

    /**
     * trigger data event
     *
     * @param mixed $data
     * @return void
     */
    public function write($data): void
    {
        $this->emit('data', [$data]);
    }

    // trigger error event
    public function error(\Exception $error): void
    {
        $this->emit('error', [$error]);
    }

    // trigger end event
    public function end(): void
    {
        $this->emit('end', []);
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function close(): void
    {
        $this->readable = false;

        $this->emit('close');
    }

    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
