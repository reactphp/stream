<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event data with a single mixed argument for incoming data
 * @event end
 * @event error with a single Exception argument for error instance
 * @event close
 */
interface ReadableStreamInterface extends EventEmitterInterface
{
    public function isReadable();
    public function pause();
    public function resume();

    /**
     * Pipes all the data from this readable source into the given writable destination.
     *
     * Automatically sends all incoming data to the destination.
     * Automatically throttles the source based on what the destination can handle.
     *
     * ```php
     * $source->pipe($dest);
     * ```
     *
     * This method returns the destination stream as-is, which can be used to
     * set up chains of piped streams:
     *
     * ```php
     * $source->pipe($decodeGzip)->pipe($filterBadWords)->pipe($dest);
     * ```
     *
     * By default, this will call `end()` on the destination stream once the
     * source stream emits an `end` event. This can be disabled like this:
     *
     * ```php
     * $source->pipe($dest, array('end' => false));
     * ```
     *
     * Note that this only applies to the `end` event.
     * If an `error` or explicit `close` event happens on the source stream,
     * you'll have to manually close the destination stream:
     *
     * ```php
     * $source->pipe($dest);
     * $source->on('close', function () use ($dest) {
     *     $dest->end('BYE!');
     * });
     * ```
     *
     * If the source stream is not readable (closed state), then this is a NO-OP.
     *
     * ```php
     * $source->close();
     * $source->pipe($dest); // NO-OP
     * ```
     *
     * If the destinantion stream is not writable (closed state), then this will simply
     * throttle (pause) the source stream:
     *
     * ```php
     * $dest->close();
     * $source->pipe($dest); // calls $source->pause()
     * ```
     *
     * Similarly, if the destination stream is closed while the pipe is still
     * active, it will also throttle (pause) the source stream:
     *
     * ```php
     * $source->pipe($dest);
     * $dest->close(); // calls $source->pause()
     * ```
     *
     * @param WritableStreamInterface $dest
     * @param array $options
     * @return WritableStreamInterface $dest stream as-is
     */
    public function pipe(WritableStreamInterface $dest, array $options = array());

    public function close();
}
