<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 *
 * Besides defining a few methods, this interface also implements the
 * `EventEmitterInterface` which allows you to react to certain events:
 *
 * drain event:
 *     Emitted if the write buffer became full previously and is now ready
 *     to accept more data.
 *
 * pipe event:
 *     Emitted whenever a readable stream is `pipe()`d into this stream
 *     with a single `ReadableStreamInterface` argument for source stream.
 *
 * error event:
 *     Emitted whenever an error occurs
 *     with a single `Exception` argument for error instance.
 *
 * close event:
 *     Emitted whenever the stream is closed.
 *
 * @see EventEmitterInterface
 * @see DuplexStreamInterface
 */
interface WritableStreamInterface extends EventEmitterInterface
{
    /**
     * Checks whether this stream is in a writable state (not closed already).
     *
     * This method can be used to check if the stream still accepts writing
     * any data or if it is ended or closed already.
     * Writing any data to a non-writable stream is a NO-OP:
     *
     * ```php
     * assert($stream->isWritable() === false);
     *
     * $stream->write('end'); // NO-OP
     * $stream->end('end'); // NO-OP
     * ```
     *
     * A successfully opened stream always MUST start in writable mode.
     *
     * Once the stream ends or closes, it MUST switch to non-writable mode.
     * This can happen any time, explicitly through `end()` or `close()` or
     * implicitly due to a remote close or an unrecoverable transmission error.
     * Once a stream has switched to non-writable mode, it MUST NOT transition
     * back to writable mode.
     *
     * If this stream is a `DuplexStreamInterface`, you should also notice
     * how the readable side of the stream also implements an `isReadable()`
     * method. Unless this is a half-open duplex stream, they SHOULD usually
     * have the same return value.
     *
     * @return bool
     */
    public function isWritable();

    /**
     * Write some data into the stream.
     *
     * A successful write MUST be confirmed with a boolean `true`, which means
     * that either the data was written (flushed) immediately or is buffered and
     * scheduled for a future write. Note that this interface gives you no
     * control over explicitly flushing the buffered data, as finding the
     * appropriate time for this is beyond the scope of this interface and left
     * up to the implementation of this interface.
     *
     * Many common streams (such as a TCP/IP connection or file-based stream)
     * may choose to buffer all given data and schedule a future flush by using
     * an underlying EventLoop to check when the resource is actually writable.
     *
     * If a stream cannot handle writing (or flushing) the data, it SHOULD emit
     * an `error` event and MAY `close()` the stream if it can not recover from
     * this error.
     *
     * If the internal buffer is full after adding `$data`, then `write()`
     * SHOULD return `false`, indicating that the caller should stop sending
     * data until the buffer `drain`s.
     *
     * Similarly, if the the stream is not writable (already in a closed state)
     * it MUST NOT process the given `$data` and SHOULD return `false`,
     * indicating that the caller should stop sending data.
     *
     * The given `$data` argument MAY be of mixed type, but it's usually
     * recommended it SHOULD be a `string` value or MAY use a type that allows
     * representation as a `string` for maximum compatibility.
     *
     * Many common streams (such as a TCP/IP connection or a file-based stream)
     * will only accept the raw (binary) payload data that is transferred over
     * the wire as chunks of `string` values.
     *
     * Due to the stream-based nature of this, the sender may send any number
     * of chunks with varying sizes. There are no guarantees that these chunks
     * will be received with the exact same framing the sender intended to send.
     * In other words, many lower-level protocols (such as TCP/IP) transfer the
     * data in chunks that may be anywhere between single-byte values to several
     * dozens of kilobytes. You may want to apply a higher-level protocol to
     * these low-level data chunks in order to achieve proper message framing.
     *
     * @param mixed|string $data
     * @return bool
     */
    public function write($data);

    /**
     * Successfully ends the stream (after optionally sending some final data).
     *
     * This method can be used to successfully end the stream, i.e. close
     * the stream after sending out all data that is currently buffered.
     *
     * ```php
     * $stream->write('hello');
     * $stream->write('world');
     * $stream->end();
     * ```
     *
     * If there's no data currently buffered and nothing to be flushed, then
     * this method MAY `close()` the stream immediately.
     *
     * If there's still data in the buffer that needs to be flushed first, then
     * this method SHOULD try to write out this data and only then `close()`
     * the stream.
     *
     * Note that this interface gives you no control over explicitly flushing
     * the buffered data, as finding the appropriate time for this is beyond the
     * scope of this interface and left up to the implementation of this
     * interface.
     *
     * Many common streams (such as a TCP/IP connection or file-based stream)
     * may choose to buffer all given data and schedule a future flush by using
     * an underlying EventLoop to check when the resource is actually writable.
     *
     * You can optionally pass some final data that is written to the stream
     * before ending the stream. If a non-`null` value is given as `$data`, then
     * this method will behave just like calling `write($data)` before ending
     * with no data.
     *
     * ```php
     * // shorter version
     * $stream->end('bye');
     *
     * // same as longer version
     * $stream->write('bye');
     * $stream->end();
     * ```
     *
     * After calling this method, the stream MUST switch into a non-writable
     * mode, see also `isWritable()`.
     * This means that no further writes are possible, so any additional
     * `write()` or `end()` calls have no effect.
     *
     * ```php
     * $stream->end();
     * assert($stream->isWritable() === false);
     *
     * $stream->write('nope'); // NO-OP
     * $stream->end(); // NO-OP
     * ```
     *
     * Note that this method should not be confused with the `close()` method.
     *
     * @param mixed|string|null $data
     * @return void
     */
    public function end($data = null);

    /**
     * Closes the stream (forcefully).
     *
     * This method can be used to forcefully close the stream, i.e. close
     * the stream without waiting for any buffered data to be flushed.
     * If there's still data in the buffer, this data SHOULD be discarded.
     *
     * ```php
     * $stream->close();
     * ```
     *
     * After calling this method, the stream MUST switch into a non-writable
     * mode, see also `isWritable()`.
     * This means that no further writes are possible, so any additional
     * `write()` or `end()` calls have no effect.
     *
     * ```php
     * $stream->close();
     * assert($stream->isWritable() === false);
     *
     * $stream->write('nope'); // NO-OP
     * $stream->end(); // NO-OP
     * ```
     *
     * Note that this method should not be confused with the `end()` method.
     * Unlike the `end()` method, this method does not take care of any existing
     * buffers and simply discards any buffer contents.
     * Likewise, this method may also be called after calling `end()` on a
     * stream in order to stop waiting for the stream to flush its final data.
     *
     * ```php
     * $stream->end();
     * $loop->addTimer(1.0, function () use ($stream) {
     *     $stream->close();
     * });
     * ```
     *
     * If this stream is a `DuplexStreamInterface`, you should also notice
     * how the readable side of the stream also implements a `close()` method.
     * In other words, after calling this method, the stream MUST switch into
     * non-writable AND non-readable mode, see also `isReadable()`.
     *
     * @return void
     * @see ReadableStreamInterface::close()
     */
    public function close();
}
