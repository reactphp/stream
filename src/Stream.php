<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;

class Stream extends EventEmitter implements DuplexStreamInterface
{
    public $bufferSize = 4096;
    public $stream;
    protected $readable = true;
    protected $writable = true;
    protected $closing = false;
    protected $loop;
    protected $buffer;

    public function __construct($stream, LoopInterface $loop)
    {
        $this->stream = $stream;
        if (!is_resource($this->stream) || get_resource_type($this->stream) !== "stream") {
             throw new InvalidArgumentException('First parameter must be a valid stream resource');
        }

        stream_set_blocking($this->stream, 0);

        $this->loop = $loop;
        $this->buffer = new Buffer($this->stream, $this->loop);

        $this->buffer->on('error', function ($error) {
            $this->emit('error', array($error, $this));
            $this->close();
        });

        $this->buffer->on('drain', function () {
            $this->emit('drain', array($this));
        });

        $this->resume();
    }

    public function isReadable()
    {
        return $this->readable;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function read()
    {
        return fread($this->stream, $this->bufferSize);
    }

    public function pause()
    {
        $this->loop->removeReadStream($this->stream);
    }

    public function resume()
    {
        if ($this->readable) {
            $this->loop->addReadStream($this->stream, array($this, 'handleData'));
        }
    }

    public function write($data)
    {
        if (!$this->writable) {
            return;
        }

        return $this->buffer->write($data);
    }

    public function close()
    {
        if (!$this->writable && !$this->closing) {
            return;
        }

        $this->closing = false;

        $this->readable = false;
        $this->writable = false;

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->loop->removeStream($this->stream);
        $this->buffer->removeAllListeners();
        $this->removeAllListeners();

        $this->handleClose();
    }

    public function end($data = null)
    {
        if (!$this->writable) {
            return;
        }

        $this->closing = true;

        $this->readable = false;
        $this->writable = false;

        $this->buffer->on('close', function () {
            $this->close();
        });

        $this->buffer->end($data);
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function handleData()
    {
        $data = $this->read();

        $this->emit('data', array($data, $this));

        if (!is_resource($this->stream) || feof($this->stream)) {
            $this->end();
        }
    }

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function getBuffer()
    {
        return $this->buffer;
    }
}
