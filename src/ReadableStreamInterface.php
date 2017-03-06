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
    /**
     * Checks whether this stream is in a readable state (not closed already).
     *
     * This method can be used to check if the stream still accepts incoming
     * data events or if it is ended or closed already.
     * Once the stream is non-readable, no further `data` or `end` events SHOULD
     * be emitted.
     *
     * ```php
     * assert($stream->isReadable() === false);
     *
     * $stream->on('data', assertNeverCalled());
     * $stream->on('end', assertNeverCalled());
     * ```
     *
     * A successfully opened stream always MUST start in readable mode.
     *
     * Once the stream ends or closes, it MUST switch to non-readable mode.
     * This can happen any time, explicitly through `close()` or
     * implicitly due to a remote close or an unrecoverable transmission error.
     * Once a stream has switched to non-readable mode, it MUST NOT transition
     * back to readable mode.
     *
     * If this stream is a `DuplexStreamInterface`, you should also notice
     * how the writable side of the stream also implements an `isWritable()`
     * method. Unless this is a half-open duplex stream, they SHOULD usually
     * have the same return value.
     *
     * @return bool
     */
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
     * Similarly, you can also pipe an instance implementing `DuplexStreamInterface`
     * into itself in order to write back all the data that is received.
     * This may be a useful feature for a TCP/IP echo service:
     *
     * ```php
     * $connection->pipe($connection);
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
