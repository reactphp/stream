<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 *
 * Besides defining a few methods, this interface also implements the
 * `EventEmitterInterface` which allows you to react to certain events:
 *
 * data event:
 *     The `data` event will be emitted whenever some data was read/received
 *     from this source stream.
 *     The event receives a single mixed argument for incoming data.
 *
 * end event:
 *     The `end` event will be emitted once the source stream has successfully
 *     reached the end of the stream (EOF).
 *     This event will only be emitted if the *end* was reached successfully, not
 *     if the stream was interrupted due to an error or explicitly closed.
 *     Also note that not all streams know the concept of a "successful end".
 *
 * error event:
 *     The `error` event will be emitted whenever an error occurs, usually while
 *     trying to read from this stream.
 *     The event receives a single `Exception` argument for the error instance.
 *
 * close event:
 *     The `close` event will be emitted once the stream closes (terminates).
 *
 * @see EventEmitterInterface
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

    /**
     * Pauses reading incoming data events.
     *
     * Removes the data source file descriptor from the event loop. This
     * allows you to throttle incoming data.
     *
     * Unless otherwise noted, a successfully opened stream SHOULD NOT start
     * in paused state.
     *
     * Once the stream is paused, no futher `data` or `end` events SHOULD
     * be emitted.
     *
     * ```php
     * $stream->pause();
     *
     * $stream->on('data', assertShouldNeverCalled());
     * $stream->on('end', assertShouldNeverCalled());
     * ```
     *
     * This method is advisory-only, though generally not recommended, the
     * stream MAY continue emitting `data` events.
     *
     * You can continue processing events by calling `resume()` again.
     *
     * Note that both methods can be called any number of times, in particular
     * calling `pause()` more than once SHOULD NOT have any effect.
     *
     * @see self::resume()
     * @return void
     */
    public function pause();

    /**
     * Resumes reading incoming data events.
     *
     * Re-attach the data source after a previous `pause()`.
     *
     * ```php
     * $stream->pause();
     *
     * $loop->addTimer(1.0, function () use ($stream) {
     *     $stream->resume();
     * });
     * ```
     *
     * Note that both methods can be called any number of times, in particular
     * calling `resume()` without a prior `pause()` SHOULD NOT have any effect.
     *
     * @see self::pause()
     * @return void
     */
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

    /**
     * Closes the stream (forcefully).
     *
     * This method can be used to (forcefully) close the stream.
     *
     * ```php
     * $stream->close();
     * ```
     *
     * After calling this method, the stream MUST switch into a non-readable
     * mode, see also `isReadable()`.
     * This means that no further `data` or `end` events SHOULD be emitted.
     *
     * ```php
     * $stream->close();
     * assert($stream->isReadable() === false);
     *
     * $stream->on('data', assertNeverCalled());
     * $stream->on('end', assertNeverCalled());
     * ```
     *
     * If this stream is a `DuplexStreamInterface`, you should also notice
     * how the writable side of the stream also implements a `close()` method.
     * In other words, after calling this method, the stream MUST switch into
     * non-writable AND non-readable mode, see also `isWritable()`.
     * Note that this method should not be confused with the `end()` method.
     *
     * @return void
     * @see WritableStreamInterface::close()
     */
    public function close();
}
