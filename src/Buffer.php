<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/** @event full-drain */
class Buffer extends EventEmitter implements WritableStreamInterface
{
    public $stream;
    public $listening = false;
    public $softLimit = 2048;
    private $writable = true;
    private $loop;
    private $data = '';

    public function __construct($stream, LoopInterface $loop)
    {
        $this->stream = $stream;
        $this->loop = $loop;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function write($data)
    {
        if (!$this->writable) {
            return;
        }

        $this->data .= $data;

        if (!$this->listening && $this->data !== '') {
            $this->listening = true;

            $this->loop->addWriteStream($this->stream, array($this, 'handleWrite'));
        }

        $belowSoftLimit = strlen($this->data) < $this->softLimit;

        return $belowSoftLimit;
    }

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->writable = false;

        if ($this->listening) {
            $this->on('full-drain', array($this, 'close'));
        } else {
            $this->close();
        }
    }

    public function close()
    {
        $this->writable = false;
        $this->listening = false;
        $this->data = '';

        $this->emit('close', array($this));
    }

    public function handleWrite()
    {
        if (!is_resource($this->stream)) {
            $this->emit('error', array(new \RuntimeException('Tried to write to invalid stream.'), $this));

            return;
        }

        $error = null;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$error) {
            $error = new \ErrorException(
                $errstr,
                0,
                $errno,
                $errfile,
                $errline
            );
        });

        $sent = fwrite($this->stream, $this->data);

        restore_error_handler();

        // Only report errors if *nothing* could be sent.
        // Any hard (permanent) error will fail to send any data at all.
        // Sending excessive amounts of data will only flush *some* data and then
        // report a temporary error (EAGAIN) which we do not raise here in order
        // to keep the stream open for further tries to write.
        // Should this turn out to be a permanent error later, it will eventually
        // send *nothing* and we can detect this.
        if ($sent === 0) {
            if ($error === null) {
                $error = new \RuntimeException('Send failed');
            }

            $this->emit('error', array(new \RuntimeException('Unable to write to stream: ' . $error->getMessage(), 0, $error), $this));

            return;
        }

        $len = strlen($this->data);
        $this->data = (string) substr($this->data, $sent);

        if ($len >= $this->softLimit && $len - $sent < $this->softLimit) {
            $this->emit('drain', array($this));
        }

        if (0 === strlen($this->data)) {
            $this->loop->removeWriteStream($this->stream);
            $this->listening = false;

            $this->emit('full-drain', array($this));
        }
    }
}
