<?php


namespace React\Stream;


interface ReadableStreamInterface
    extends ReadableStreamBaseInterface
{

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
     * Once the pipe is set up successfully, the destination stream MUST emit
     * a `pipe` event with this source stream an event argument.
     *
     * @param WritableStreamInterface $dest
     * @param array $options
     * @return WritableStreamInterface $dest stream as-is
     */
    public function pipe(WritableStreamInterface $dest, array $options = array());

}