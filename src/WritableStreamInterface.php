<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event drain
 * @event error with single Exeption argument for error instance
 * @event close
 * @event pipe with single ReadableStreamInterface argument for source stream
 */
interface WritableStreamInterface extends EventEmitterInterface
{
    public function isWritable();
    public function write($data);
    public function end($data = null);
    public function close();
}
