<?php

namespace React\Stream;

use Evenement\EventEmitterInterface;

/**
 * The `StreamServerInterface` is responsible for providing an interface for
 * stream servers (as created by `stream_socket_server`:
 *
 * ```php
 * $loop   = Factory::create();
 * $server = new StreamServerStream($socket, $loop);
 * if (!$server->isReadable())
 * {
 *     throw new \Exception('Cannot read from socket');
 * }
 * ```
 *
 * Besides defining a few methods, this interface also implements the
 * `EventEmitterInterface` which allows you to react to certain events:
 *
 * new event:
 *     The `new` event will be emitted whenever a new client connects to the
 *     stream server.
 *     The event receives three arguments:
 *     - the stream resource of the new connection
 *     - the peername as returned by `stream_socket_accept`
 *     - the server object, which gives you access to the loop
 *
 *     ```php
 *     $stream->on('new', function ($stream, $peername, $server) {
 *         $client = new DuplexResourceStream($stream, $server->loop);
 *     });
 *     ```
 *
 *     This event MAY be emitted any number of times, which may be zero times if
 *     no client ever connects.
 *     It SHOULD not be emitted after an `end` or `close` event.
 *
 *     The given `$stream` argument may be used as input.
 *
 * error event:
 *     The `error` event will be emitted once a fatal error occurs, usually while
 *     trying to read from this stream.
 *     The event receives a single `Exception` argument for the error instance.
 *
 *     ```php
 *     $stream->on('error', function (Exception $e) {
 *         echo 'Error: ' . $e->getMessage() . PHP_EOL;
 *     });
 *     ```
 *
 *     This event SHOULD be emitted once the stream detects a fatal error, such
 *     as a fatal transmission error or after an unexpected `data` or premature
 *     `end` event.
 *     It SHOULD NOT be emitted after a previous `error` or `close` event.
 *     It MUST NOT be emitted if this is not a fatal error condition, such as
 *     a temporary network issue that did not cause any data to be lost.
 *
 *     After the stream errors, it MUST close the stream and SHOULD thus be
 *     followed by a `close` event and then switch to non-readable mode, see
 *     also `close()` and `isReadable()`.
 *
 *     Many common streams (such as a TCP/IP connection or a file-based stream)
 *     only deal with data transmission and do not make assumption about data
 *     boundaries (such as unexpected `data` or premature `end` events).
 *     In other words, many lower-level protocols (such as TCP/IP) may choose
 *     to only emit this for a fatal transmission error once and will then
 *     close (terminate) the stream in response.
 *
 *     If this stream is a `DuplexStreamInterface`, you should also notice
 *     how the writable side of the stream also implements an `error` event.
 *     In other words, an error may occur while either reading or writing the
 *     stream which should result in the same error processing.
 *
 * close event:
 *     The `close` event will be emitted once the stream closes (terminates).
 *
 *     ```php
 *     $stream->on('close', function () {
 *         echo 'CLOSED';
 *     });
 *     ```
 *
 *     This event SHOULD be emitted once or never at all, depending on whether
 *     the stream ever terminates.
 *     It SHOULD NOT be emitted after a previous `close` event.
 *
 *     After the stream is closed, it MUST switch to non-readable mode,
 *     see also `isReadable()`.
 *
 *     Unlike the `end` event, this event SHOULD be emitted whenever the stream
 *     closes, irrespective of whether this happens implicitly due to an
 *     unrecoverable error or explicitly when either side closes the stream.
 *     If you only want to detect a *successful* end, you should use the `end`
 *     event instead.
 *
 *     Many common streams (such as a TCP/IP connection or a file-based stream)
 *     will likely choose to emit this event after reading a *successful* `end`
 *     event or after a fatal transmission `error` event.
 *
 *     If this stream is a `DuplexStreamInterface`, you should also notice
 *     how the writable side of the stream also implements a `close` event.
 *     In other words, after receiving this event, the stream MUST switch into
 *     non-writable AND non-readable mode, see also `isWritable()`.
 *     Note that this event should not be confused with the `end` event.
 *
 * The event callback functions MUST be a valid `callable` that obeys strict
 * parameter definitions and MUST accept event parameters exactly as documented.
 * The event callback functions MUST NOT throw an `Exception`.
 * The return value of the event callback functions will be ignored and has no
 * effect, so for performance reasons you're recommended to not return any
 * excessive data structures.
 *
 * Every implementation of this interface MUST follow these event semantics in
 * order to be considered a well-behaving stream.
 *
 * > Note that higher-level implementations of this interface may choose to
 *   define additional events with dedicated semantics not defined as part of
 *   this low-level stream specification. Conformance with these event semantics
 *   is out of scope for this interface, so you may also have to refer to the
 *   documentation of such a higher-level implementation.
 *
 * @see EventEmitterInterface
 * @see ReadableStreamBaseInterface
 */
interface StreamServerInterface extends ReadableStreamBaseInterface
{

}
