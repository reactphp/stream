<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data
 * @event end
 * @event error
 * @event close
 */
interface ReadableStreamInterface extends EventEmitterInterface
{
    public function isReadable();
    public function read();
    public function pause();
    public function resume();
    public function pipe(WritableStreamInterface $dest, array $options = array());
    public function close();
}
