<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * @event drain
 * @event error with a single Exeption argument for error instance
 * @event close
 * @event pipe with a single ReadableStreamInterface argument for source stream
 */
interface WritableStreamInterface extends EventEmitterInterface
{
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

    public function end($data = null);
    public function close();
}
