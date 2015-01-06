<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;

/**
 * @event full
 * @event full-drain
 */
class Buffer extends EventEmitter implements WritableStreamInterface
{
    public $stream;
    public $listening = false;
    public $softLimit = 2048;
    private $writable = true;
    private $loop;
    private $data = '';
    private $byteProgress = 0;
    private $deferredWrites = array();

    private $lastError = array(
        'number'  => 0,
        'message' => '',
        'file'    => '',
        'line'    => 0,
    );

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

        if (strlen($data) == 0) {
            return new FulfilledPromise();
        }

        $this->data .= $data;

        // $data is written when the byte-progress reaches $writtenWhen bytes
        $deferred = new Deferred();
        $writtenWhen = $this->byteProgress + strlen($this->data);
        $this->deferredWrites[$writtenWhen] = $deferred;
        ksort($this->deferredWrites);

        if (!$this->listening) {
            $this->listening = true;

            $this->loop->addWriteStream($this->stream, array($this, 'handleWrite'));
        }

        $bufferFull = strlen($this->data) >= $this->softLimit;
        if ($bufferFull) {
            $this->emit('full', [$this]);
        }

        return $deferred->promise();
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

        $this->emit('close', [$this]);
    }

    public function handleWrite()
    {
        if (!is_resource($this->stream)) {
            $this->emit('error', array(new \RuntimeException('Tried to write to invalid stream.'), $this));

            return;
        }

        // write, but handle fwrite's errors internally
        set_error_handler(array($this, 'errorHandler'));
        $sent = fwrite($this->stream, $this->data);
        restore_error_handler();

        // handle write-error
        if (false === $sent) {
            $this->emit('error', array(
                new \ErrorException(
                    $this->lastError['message'],
                    0,
                    $this->lastError['number'],
                    $this->lastError['file'],
                    $this->lastError['line']
                ),
                $this
            ));

            return;
        }

        // handle EOF
        if (0 === $sent && feof($this->stream)) {
            $this->emit('error', array(new \RuntimeException('Tried to write to closed stream.'), $this));

            return;
        }

        // update buffer state
        $this->byteProgress += $sent;
        $this->data = (string) substr($this->data, $sent);

        // resolve transmission promises
        foreach ($this->deferredWrites as $threshold => $promise) {
            if ($threshold <= $this->byteProgress) {
                unset($this->deferredWrites[$threshold]);
                ksort($this->deferredWrites);
                $promise->resolve();
            } else {
                break;
            }
        }

        // check if buffer was full and is writable again
        $len = strlen($this->data);
        if ($len < $this->softLimit && ($len + $sent) >= $this->softLimit) {
            $this->emit('drain', [$this]);
        }

        // check if buffer is empty
        if (0 === strlen($this->data)) {
            $this->loop->removeWriteStream($this->stream);
            $this->listening = false;

            $this->emit('full-drain', [$this]);
        }
    }

    private function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->lastError['number']  = $errno;
        $this->lastError['message'] = $errstr;
        $this->lastError['file']    = $errfile;
        $this->lastError['line']    = $errline;
    }
}
