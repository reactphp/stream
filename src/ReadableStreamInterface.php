<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data with single mixed argument for incoming data
 * @event end
 * @event error with single Exception argument for error instance
 * @event close
 */
interface ReadableStreamInterface extends EventEmitterInterface
{
    public function isReadable();
    public function pause();
    public function resume();
    public function pipe(WritableStreamInterface $dest, array $options = array());
    public function close();
}
