<?php

namespace React\Stream;

use Evenement\EventEmitter;

final class CompositeStream extends EventEmitter implements DuplexStreamInterface
{
    /** @var ReadableStreamInterface */
    private $readable;

    /** @var WritableStreamInterface */
    private $writable;

    /** @var bool */
    private $closed = false;

    public function __construct(ReadableStreamInterface $readable, WritableStreamInterface $writable)
    {
        $this->readable = $readable;
        $this->writable = $writable;

        if (!$readable->isReadable() || !$writable->isWritable()) {
            $this->close();
            return;
        }

        Util::forwardEvents($this->readable, $this, ['data', 'end', 'error']);
        Util::forwardEvents($this->writable, $this, ['drain', 'error', 'pipe']);

        $this->readable->on('close', [$this, 'close']);
        $this->writable->on('close', [$this, 'close']);
    }

    public function isReadable(): bool
    {
        return $this->readable->isReadable();
    }

    public function pause(): void
    {
        $this->readable->pause();
    }

    public function resume(): void
    {
        if (!$this->writable->isWritable()) {
            return;
        }

        $this->readable->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return Util::pipe($this, $dest, $options);
    }

    public function isWritable(): bool
    {
        return $this->writable->isWritable();
    }

    public function write($data): bool
    {
        return $this->writable->write($data);
    }

    public function end($data = null): void
    {
        $this->readable->pause();
        $this->writable->end($data);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->readable->close();
        $this->writable->close();

        $this->emit('close');
        $this->removeAllListeners();
    }
}
