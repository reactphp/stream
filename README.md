# Stream Component

[![Build Status](https://travis-ci.org/reactphp/stream.svg?branch=master)](https://travis-ci.org/reactphp/stream)

Event-driven readable and writable streams for non-blocking I/O in [ReactPHP](https://reactphp.org/).

In order to make the [EventLoop](https://github.com/reactphp/event-loop)
easier to use, this component introduces the concept of "streams".
They are very similar to the streams found in PHP itself,
but have an interface more suited for async, non-blocking I/O.
Mainly it provides interfaces for readable and writable streams, plus a file
descriptor based implementation with an in-memory write buffer.

**Table of contents**

* [API](#api)
  * [ReadableStreamInterface](#readablestreaminterface)
    * [data event](#data-event)
    * [end event](#end-event)
    * [error event](#error-event)
    * [close event](#close-event)
    * [isReadable()](#isreadable)
    * [pause()](#pause)
    * [resume()](#resume)
    * [pipe()](#pipe)
    * [close()](#close)
  * [WritableStreamInterface](#writablestreaminterface)
    * [drain event](#drain-event)
    * [pipe event](#pipe-event)
    * [error event](#error-event-1)
    * [close event](#close-event-1)
    * [isWritable()](#iswritable)
    * [write()](#write)
    * [end()](#end)
    * [close()](#close-1)
  * [DuplexStreamInterface](#duplexstreaminterface)
  * [ReadableResourceStream](#readableresourcestream)
  * [WritableResourceStream](#writableresourcestream)
  * [DuplexResourceStream](#duplexresourcestream)
* [Usage](#usage)
* [Install](#install)
* [Tests](#tests)
* [License](#license)

## API

### ReadableStreamInterface

The `ReadableStreamInterface` is responsible for providing an interface for
read-only streams and the readable side of duplex streams.

Besides defining a few methods, this interface also implements the
`EventEmitterInterface` which allows you to react to certain events.

#### data event

The `data` event will be emitted whenever some data was read/received
from this source stream.
The event receives a single mixed argument for incoming data.

```php
$stream->on('data', function ($data) {
    echo $data;
});
```

This event MAY be emitted any number of times, which may be zero times if
this stream does not send any data at all.
It SHOULD not be emitted after an `end` or `close` event.

The given `$data` argument may be of mixed type, but it's usually
recommended it SHOULD be a `string` value or MAY use a type that allows
representation as a `string` for maximum compatibility.

Many common streams (such as a TCP/IP connection or a file-based stream)
will emit the raw (binary) payload data that is received over the wire as
chunks of `string` values.

Due to the stream-based nature of this, the sender may send any number
of chunks with varying sizes. There are no guarantees that these chunks
will be received with the exact same framing the sender intended to send.
In other words, many lower-level protocols (such as TCP/IP) transfer the
data in chunks that may be anywhere between single-byte values to several
dozens of kilobytes. You may want to apply a higher-level protocol to
these low-level data chunks in order to achieve proper message framing.
  
#### end event

The `end` event will be emitted once the source stream has successfully
reached the end of the stream (EOF).

```php
$stream->on('end', function () {
    echo 'END';
});
```

This event SHOULD be emitted once or never at all, depending on whether
a successful end was detected.
It SHOULD NOT be emitted after a previous `end` or `close` event.
It MUST NOT be emitted if the stream closes due to a non-successful
end, such as after a previous `error` event.

After the stream is ended, it MUST switch to non-readable mode,
see also `isReadable()`.

This event will only be emitted if the *end* was reached successfully,
not if the stream was interrupted by an unrecoverable error or explicitly
closed. Not all streams know this concept of a "successful end".
Many use-cases involve detecting when the stream closes (terminates)
instead, in this case you should use the `close` event.
After the stream emits an `end` event, it SHOULD usually be followed by a
`close` event.

Many common streams (such as a TCP/IP connection or a file-based stream)
will emit this event if either the remote side closes the connection or
a file handle was successfully read until reaching its end (EOF).

Note that this event should not be confused with the `end()` method.
This event defines a successful end *reading* from a source stream, while
the `end()` method defines *writing* a successful end to a destination
stream.

#### error event

The `error` event will be emitted whenever an error occurs, usually while
trying to read from this stream.
The event receives a single `Exception` argument for the error instance.

```php
$server->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

This event MAY be emitted any number of times, which should be zero 
times if this is a stream that is successfully terminated.
It SHOULD be emitted whenever the stream detects an error, such as a
transmission error or after an unexpected `data` or premature `end` event.
It SHOULD NOT be emitted after a `close` event.

Many common streams (such as a TCP/IP connection or a file-based stream)
only deal with data transmission and do not make assumption about data
boundaries (such as unexpected `data` or premature `end` events).
In other words, many lower-level protocols (such as TCP/IP) may choose
to only emit this for a fatal transmission error once and will thus
likely close (terminate) the stream in response.
If this is a fatal error that results in the stream being closed, it
SHOULD be followed by a `close` event.

Other higher-level protocols may choose to keep the stream alive after
this event, if they can recover from an error condition.

If this stream is a `DuplexStreamInterface`, you should also notice
how the writable side of the stream also implements an `error` event.
In other words, an error may occur while either reading or writing the
stream which should result in the same error processing.

#### close event

The `close` event will be emitted once the stream closes (terminates).

```php
$stream->on('close', function () {
    echo 'CLOSED';
});
```

This event SHOULD be emitted once or never at all, depending on whether
the stream ever terminates.
It SHOULD NOT be emitted after a previous `close` event.

After the stream is closed, it MUST switch to non-readable mode,
see also `isReadable()`.

Unlike the `end` event, this event SHOULD be emitted whenever the stream
closes, irrespective of whether this happens implicitly due to an
unrecoverable error or explicitly when either side closes the stream.
If you only want to detect a *succesful* end, you should use the `end`
event instead.

Many common streams (such as a TCP/IP connection or a file-based stream)
will likely choose to emit this event after reading a *successful* `end`
event or after a fatal transmission `error` event.

If this stream is a `DuplexStreamInterface`, you should also notice
how the writable side of the stream also implements a `close` event.
In other words, after receiving this event, the stream MUST switch into
non-writable AND non-readable mode, see also `isWritable()`.
Note that this event should not be confused with the `end` event.

#### isReadable()

The `isReadable(): bool` method can be used to
check whether this stream is in a readable state (not closed already).

This method can be used to check if the stream still accepts incoming
data events or if it is ended or closed already.
Once the stream is non-readable, no further `data` or `end` events SHOULD
be emitted.

```php
assert($stream->isReadable() === false);

$stream->on('data', assertNeverCalled());
$stream->on('end', assertNeverCalled());
```

A successfully opened stream always MUST start in readable mode.

Once the stream ends or closes, it MUST switch to non-readable mode.
This can happen any time, explicitly through `close()` or
implicitly due to a remote close or an unrecoverable transmission error.
Once a stream has switched to non-readable mode, it MUST NOT transition
back to readable mode.

If this stream is a `DuplexStreamInterface`, you should also notice
how the writable side of the stream also implements an `isWritable()`
method. Unless this is a half-open duplex stream, they SHOULD usually
have the same return value.

#### pause()

The `pause(): void` method can be used to
pause reading incoming data events.

Removes the data source file descriptor from the event loop. This
allows you to throttle incoming data.

Unless otherwise noted, a successfully opened stream SHOULD NOT start
in paused state.

Once the stream is paused, no futher `data` or `end` events SHOULD
be emitted.

```php
$stream->pause();

$stream->on('data', assertShouldNeverCalled());
$stream->on('end', assertShouldNeverCalled());
```

This method is advisory-only, though generally not recommended, the
stream MAY continue emitting `data` events.

You can continue processing events by calling `resume()` again.

Note that both methods can be called any number of times, in particular
calling `pause()` more than once SHOULD NOT have any effect.

See also `resume()`.

#### resume()

The `resume(): void` method can be used to
resume reading incoming data events.

Re-attach the data source after a previous `pause()`.

```php
$stream->pause();

$loop->addTimer(1.0, function () use ($stream) {
    $stream->resume();
});
```

Note that both methods can be called any number of times, in particular
calling `resume()` without a prior `pause()` SHOULD NOT have any effect.
 
See also `pause()`.

#### pipe()

The `pipe(WritableStreamInterface $dest, array $options = [])` method can be used to
pipe all the data from this readable source into the given writable destination.

Automatically sends all incoming data to the destination.
Automatically throttles the source based on what the destination can handle.

```php
$source->pipe($dest);
```

Similarly, you can also pipe an instance implementing `DuplexStreamInterface`
into itself in order to write back all the data that is received.
This may be a useful feature for a TCP/IP echo service:

```php
$connection->pipe($connection);
```

This method returns the destination stream as-is, which can be used to
set up chains of piped streams:

```php
$source->pipe($decodeGzip)->pipe($filterBadWords)->pipe($dest);
```

By default, this will call `end()` on the destination stream once the
source stream emits an `end` event. This can be disabled like this:

```php
$source->pipe($dest, array('end' => false));
```

Note that this only applies to the `end` event.
If an `error` or explicit `close` event happens on the source stream,
you'll have to manually close the destination stream:

```php
$source->pipe($dest);
$source->on('close', function () use ($dest) {
    $dest->end('BYE!');
});
```

If the source stream is not readable (closed state), then this is a NO-OP.

```php
$source->close();
$source->pipe($dest); // NO-OP
```

If the destinantion stream is not writable (closed state), then this will simply
throttle (pause) the source stream:

```php
$dest->close();
$source->pipe($dest); // calls $source->pause()
```

Similarly, if the destination stream is closed while the pipe is still
active, it will also throttle (pause) the source stream:

```php
$source->pipe($dest);
$dest->close(); // calls $source->pause()
```

Once the pipe is set up successfully, the destination stream MUST emit
a `pipe` event with this source stream an event argument.

#### close()

The `close(): void` method can be used to
close the stream (forcefully).

This method can be used to (forcefully) close the stream.

```php
$stream->close();
```

Once the stream is closed, it SHOULD emit a `close` event.
Note that this event SHOULD NOT be emitted more than once, in particular
if this method is called multiple times.

After calling this method, the stream MUST switch into a non-readable
mode, see also `isReadable()`.
This means that no further `data` or `end` events SHOULD be emitted.

```php
$stream->close();
assert($stream->isReadable() === false);

$stream->on('data', assertNeverCalled());
$stream->on('end', assertNeverCalled());
```

If this stream is a `DuplexStreamInterface`, you should also notice
how the writable side of the stream also implements a `close()` method.
In other words, after calling this method, the stream MUST switch into
non-writable AND non-readable mode, see also `isWritable()`.
Note that this method should not be confused with the `end()` method.

### WritableStreamInterface

The `WritableStreamInterface` is responsible for providing an interface for
write-only streams and the writable side of duplex streams.

Besides defining a few methods, this interface also implements the
`EventEmitterInterface` which allows you to react to certain events.

#### drain event

The `drain` event will be emitted whenever the write buffer became full
previously and is now ready to accept more data.

```php
$stream->on('drain', function () use ($stream) {
    echo 'Stream is now ready to accept more data';
});
```

This event SHOULD be emitted once every time the buffer became full
previously and is now ready to accept more data.
In other words, this event MAY be emitted any number of times, which may
be zero times if the buffer never became full in the first place.
This event SHOULD NOT be emitted if the buffer has not become full
previously.

This event is mostly used internally, see also `write()` for more details.

#### pipe event

The `pipe` event will be emitted whenever a readable stream is `pipe()`d
into this stream.
The event receives a single `ReadableStreamInterface` argument for the
source stream.

```php
$stream->on('pipe', function (ReadableStreamInterface $source) use ($stream) {
    echo 'Now receiving piped data';

    // explicitly close target if source emits an error
    $source->on('error', function () use ($stream) {
        $stream->close();
    });
});

$source->pipe($stream);
```

This event MUST be emitted once for each readable stream that is
successfully piped into this destination stream.
In other words, this event MAY be emitted any number of times, which may
be zero times if no stream is ever piped into this stream.
This event MUST NOT be emitted if either the source is not readable
(closed already) or this destination is not writable (closed already).

This event is mostly used internally, see also `pipe()` for more details.

#### error event

The `error` event will be emitted whenever an error occurs, usually while
trying to write to this stream.
The event receives a single `Exception` argument for the error instance.

```php
$stream->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
```

This event MAY be emitted any number of times, which should be zero
times if this is a stream that is successfully terminated.
It SHOULD be emitted whenever the stream detects an error, such as a
transmission error.
It SHOULD NOT be emitted after a `close` event.

Many common streams (such as a TCP/IP connection or a file-based stream)
only deal with data transmission and may choose
to only emit this for a fatal transmission error once and will thus
likely close (terminate) the stream in response.
If this is a fatal error that results in the stream being closed, it
SHOULD be followed by a `close` event.

Other higher-level protocols may choose to keep the stream alive after
this event, if they can recover from an error condition.

If this stream is a `DuplexStreamInterface`, you should also notice
how the readable side of the stream also implements an `error` event.
In other words, an error may occur while either reading or writing the
stream which should result in the same error processing.

#### close event

The `close` event will be emitted once the stream closes (terminates).

```php
$stream->on('close', function () {
    echo 'CLOSED';
});
```

This event SHOULD be emitted once or never at all, depending on whether
the stream ever terminates.
It SHOULD NOT be emitted after a previous `close` event.

After the stream is closed, it MUST switch to non-writable mode,
see also `isWritable()`.

This event SHOULD be emitted whenever the stream closes, irrespective of
whether this happens implicitly due to an unrecoverable error or
explicitly when either side closes the stream.

Many common streams (such as a TCP/IP connection or a file-based stream)
will likely choose to emit this event after flushing the buffer from
the `end()` method, after receiving a *successful* `end` event or after
a fatal transmission `error` event.

If this stream is a `DuplexStreamInterface`, you should also notice
how the readable side of the stream also implements a `close` event.
In other words, after receiving this event, the stream MUST switch into
non-writable AND non-readable mode, see also `isReadable()`.
Note that this event should not be confused with the `end` event.

#### isWritable()

The `isWritable(): bool` method can be used to
check whether this stream is in a writable state (not closed already).

This method can be used to check if the stream still accepts writing
any data or if it is ended or closed already.
Writing any data to a non-writable stream is a NO-OP:

```php
assert($stream->isWritable() === false);

$stream->write('end'); // NO-OP
$stream->end('end'); // NO-OP
```

A successfully opened stream always MUST start in writable mode.

Once the stream ends or closes, it MUST switch to non-writable mode.
This can happen any time, explicitly through `end()` or `close()` or
implicitly due to a remote close or an unrecoverable transmission error.
Once a stream has switched to non-writable mode, it MUST NOT transition
back to writable mode.

If this stream is a `DuplexStreamInterface`, you should also notice
how the readable side of the stream also implements an `isReadable()`
method. Unless this is a half-open duplex stream, they SHOULD usually
have the same return value.

#### write()

The `write(mixed $data): bool` method can be used to
write some data into the stream.

A successful write MUST be confirmed with a boolean `true`, which means
that either the data was written (flushed) immediately or is buffered and
scheduled for a future write. Note that this interface gives you no
control over explicitly flushing the buffered data, as finding the
appropriate time for this is beyond the scope of this interface and left
up to the implementation of this interface.

Many common streams (such as a TCP/IP connection or file-based stream)
may choose to buffer all given data and schedule a future flush by using
an underlying EventLoop to check when the resource is actually writable.

If a stream cannot handle writing (or flushing) the data, it SHOULD emit
an `error` event and MAY `close()` the stream if it can not recover from
this error.

If the internal buffer is full after adding `$data`, then `write()`
SHOULD return `false`, indicating that the caller should stop sending
data until the buffer drains.
The stream SHOULD send a `drain` event once the buffer is ready to accept
more data.

Similarly, if the the stream is not writable (already in a closed state)
it MUST NOT process the given `$data` and SHOULD return `false`,
indicating that the caller should stop sending data.

The given `$data` argument MAY be of mixed type, but it's usually
recommended it SHOULD be a `string` value or MAY use a type that allows
representation as a `string` for maximum compatibility.

Many common streams (such as a TCP/IP connection or a file-based stream)
will only accept the raw (binary) payload data that is transferred over
the wire as chunks of `string` values.

Due to the stream-based nature of this, the sender may send any number
of chunks with varying sizes. There are no guarantees that these chunks
will be received with the exact same framing the sender intended to send.
In other words, many lower-level protocols (such as TCP/IP) transfer the
data in chunks that may be anywhere between single-byte values to several
dozens of kilobytes. You may want to apply a higher-level protocol to
these low-level data chunks in order to achieve proper message framing.

#### end()

The `end(mixed $data = null): void` method can be used to
successfully end the stream (after optionally sending some final data).

This method can be used to successfully end the stream, i.e. close
the stream after sending out all data that is currently buffered.

```php
$stream->write('hello');
$stream->write('world');
$stream->end();
```

If there's no data currently buffered and nothing to be flushed, then
this method MAY `close()` the stream immediately.

If there's still data in the buffer that needs to be flushed first, then
this method SHOULD try to write out this data and only then `close()`
the stream.
Once the stream is closed, it SHOULD emit a `close` event.

Note that this interface gives you no control over explicitly flushing
the buffered data, as finding the appropriate time for this is beyond the
scope of this interface and left up to the implementation of this
interface.

Many common streams (such as a TCP/IP connection or file-based stream)
may choose to buffer all given data and schedule a future flush by using
an underlying EventLoop to check when the resource is actually writable.

You can optionally pass some final data that is written to the stream
before ending the stream. If a non-`null` value is given as `$data`, then
this method will behave just like calling `write($data)` before ending
with no data.

```php
// shorter version
$stream->end('bye');

// same as longer version
$stream->write('bye');
$stream->end();
```

After calling this method, the stream MUST switch into a non-writable
mode, see also `isWritable()`.
This means that no further writes are possible, so any additional
`write()` or `end()` calls have no effect.

```php
$stream->end();
assert($stream->isWritable() === false);

$stream->write('nope'); // NO-OP
$stream->end(); // NO-OP
```

If this stream is a `DuplexStreamInterface`, calling this method SHOULD
also end its readable side, unless the stream supports half-open mode.
In other words, after calling this method, these streams SHOULD switch
into non-writable AND non-readable mode, see also `isReadable()`.
This implies that in this case, the stream SHOULD NOT emit any `data`
or `end` events anymore.
Streams MAY choose to use the `pause()` method logic for this, but
special care may have to be taken to ensure a following call to the
`resume()` method SHOULD NOT continue emitting readable events.

Note that this method should not be confused with the `close()` method.

#### close()

The `close(): void` method can be used to
close the stream (forcefully).

This method can be used to forcefully close the stream, i.e. close
the stream without waiting for any buffered data to be flushed.
If there's still data in the buffer, this data SHOULD be discarded.

```php
$stream->close();
```

Once the stream is closed, it SHOULD emit a `close` event.
Note that this event SHOULD NOT be emitted more than once, in particular
if this method is called multiple times.

After calling this method, the stream MUST switch into a non-writable
mode, see also `isWritable()`.
This means that no further writes are possible, so any additional
`write()` or `end()` calls have no effect.

```php
$stream->close();
assert($stream->isWritable() === false);

$stream->write('nope'); // NO-OP
$stream->end(); // NO-OP
```

Note that this method should not be confused with the `end()` method.
Unlike the `end()` method, this method does not take care of any existing
buffers and simply discards any buffer contents.
Likewise, this method may also be called after calling `end()` on a
stream in order to stop waiting for the stream to flush its final data.

```php
$stream->end();
$loop->addTimer(1.0, function () use ($stream) {
    $stream->close();
});
```

If this stream is a `DuplexStreamInterface`, you should also notice
how the readable side of the stream also implements a `close()` method.
In other words, after calling this method, the stream MUST switch into
non-writable AND non-readable mode, see also `isReadable()`.

### DuplexStreamInterface

The `DuplexStreamInterface` is responsible for providing an interface for
duplex streams (both readable and writable).

It builds on top of the existing interfaces for readable and writable streams
and follows the exact same method and event semantics.
If you're new to this concept, you should look into the
`ReadableStreamInterface` and `WritableStreamInterface` first.

Besides defining a few methods, this interface also implements the
`EventEmitterInterface` which allows you to react to the same events defined
on the `ReadbleStreamInterface` and `WritableStreamInterface`.

See also [`ReadableStreamInterface`](#readablestreaminterface) and
[`WritableStreamInterface`](#writablestreaminterface) for more details.

### ReadableResourceStream

The `ReadableResourceStream` is a concrete implementation of the
[`ReadableStreamInterface`](#readablestreaminterface) for PHP's stream resources.

This can be used to represent a read-only resource like a file stream opened in
readable mode or a stream such as `STDIN`:

```php
$stream = new ReadableResourceStream(STDIN, $loop);
$stream->on('data', function ($chunk) {
    echo $chunk;
});
$stream->on('end', function () {
    echo 'END';
});
```

See also [`ReadableStreamInterface`](#readablestreaminterface) for more details.

The first parameter given to the constructor MUST be a valid stream resource
that is opened in reading mode (e.g. `fopen()` mode `r`).
Otherwise, it will throw an `InvalidArgumentException`:

```php
// throws InvalidArgumentException
$stream = new ReadableResourceStream(false, $loop);
```

See also the [`DuplexResourceStream`](#readableresourcestream) for read-and-write
stream resources otherwise.

Internally, this class tries to enable non-blocking mode on the stream resource
which may not be supported for all stream resources.
Most notably, this is not supported by pipes on Windows (STDIN etc.).
If this fails, it will throw a `RuntimeException`:

```php
// throws RuntimeException on Windows
$stream = new ReadableResourceStream(STDIN, $loop);
```

Once the constructor is called with a valid stream resource, this class will
take care of the underlying stream resource.
You SHOULD only use its public API and SHOULD NOT interfere with the underlying
stream resource manually.
Should you need to access the underlying stream resource, you can use the public
`$stream` property like this:

```php
var_dump(stream_get_meta_data($stream->stream));
```

The `$bufferSize` property controls the maximum buffer size in bytes to read
at once from the stream.
This value SHOULD NOT be changed unless you know what you're doing.
This can be a positive number which means that up to X bytes will be read
at once from the underlying stream resource. Note that the actual number
of bytes read may be lower if the stream resource has less than X bytes
currently available.
This can be `null` which means "read everything available" from the
underlying stream resource.
This should read until the stream resource is not readable anymore
(i.e. underlying buffer drained), note that this does not neccessarily
mean it reached EOF.

```php
$stream->bufferSize = 8192;
```

### WritableResourceStream

The `WritableResourceStream` is a concrete implementation of the
[`WritableStreamInterface`](#writablestreaminterface) for PHP's stream resources.

This can be used to represent a write-only resource like a file stream opened in
writable mode or a stream such as `STDOUT` or `STDERR`:

```php
$stream = new WritableResourceStream(STDOUT, $loop);
$stream->write('hello!');
$stream->end();
```

See also [`WritableStreamInterface`](#writablestreaminterface) for more details.

The first parameter given to the constructor MUST be a valid stream resource
that is opened for writing.
Otherwise, it will throw an `InvalidArgumentException`:

```php
// throws InvalidArgumentException
$stream = new WritableResourceStream(false, $loop);
```

See also the [`DuplexResourceStream`](#readableresourcestream) for read-and-write
stream resources otherwise.

Internally, this class tries to enable non-blocking mode on the stream resource
which may not be supported for all stream resources.
Most notably, this is not supported by pipes on Windows (STDOUT, STDERR etc.).
If this fails, it will throw a `RuntimeException`:

```php
// throws RuntimeException on Windows
$stream = new WritableResourceStream(STDOUT, $loop);
```

Once the constructor is called with a valid stream resource, this class will
take care of the underlying stream resource.
You SHOULD only use its public API and SHOULD NOT interfere with the underlying
stream resource manually.
Should you need to access the underlying stream resource, you can use the public
`$stream` property like this:

```php
var_dump(stream_get_meta_data($stream->stream));
```

Any `write()` calls to this class will not be performaned instantly, but will
be performaned asynchronously, once the EventLoop reports the stream resource is
ready to accept data.
For this, it uses an in-memory buffer string to collect all outstanding writes.
This buffer has a soft-limit applied which defines how much data it is willing
to accept before the caller SHOULD stop sending further data.
It currently defaults to 64 KiB and can be controlled through the public
`$softLimit` property like this:

```php
$stream->softLimit = 8192;
```

See also [`write()`](#write) for more details.

### DuplexResourceStream

The `DuplexResourceStream` is a concrete implementation of the
[`DuplexStreamInterface`](#duplexstreaminterface) for PHP's stream resources.

This can be used to represent a read-and-write resource like a file stream opened
in read and write mode mode or a stream such as a TCP/IP connection:

```php
$conn = stream_socket_client('tcp://google.com:80');
$stream = new DuplexResourceStream($conn, $loop);
$stream->write('hello!');
$stream->end();
```

See also [`DuplexStreamInterface`](#duplexstreaminterface) for more details.

The first parameter given to the constructor MUST be a valid stream resource
that is opened for reading *and* writing.
Otherwise, it will throw an `InvalidArgumentException`:

```php
// throws InvalidArgumentException
$stream = new DuplexResourceStream(false, $loop);
```

See also the [`ReadableResourceStream`](#readableresourcestream) for read-only
and the [`WritableResourceStream`](#writableresourcestream) for write-only
stream resources otherwise.

Internally, this class tries to enable non-blocking mode on the stream resource
which may not be supported for all stream resources.
Most notably, this is not supported by pipes on Windows (STDOUT, STDERR etc.).
If this fails, it will throw a `RuntimeException`:

```php
// throws RuntimeException on Windows
$stream = new DuplexResourceStream(STDOUT, $loop);
```

Once the constructor is called with a valid stream resource, this class will
take care of the underlying stream resource.
You SHOULD only use its public API and SHOULD NOT interfere with the underlying
stream resource manually.
Should you need to access the underlying stream resource, you can use the public
`$stream` property like this:

```php
var_dump(stream_get_meta_data($stream->stream));
```

The `$bufferSize` property controls the maximum buffer size in bytes to read
at once from the stream.
This value SHOULD NOT be changed unless you know what you're doing.
This can be a positive number which means that up to X bytes will be read
at once from the underlying stream resource. Note that the actual number
of bytes read may be lower if the stream resource has less than X bytes
currently available.
This can be `null` which means "read everything available" from the
underlying stream resource.
This should read until the stream resource is not readable anymore
(i.e. underlying buffer drained), note that this does not neccessarily
mean it reached EOF.

```php
$stream->bufferSize = 8192;
```

Any `write()` calls to this class will not be performaned instantly, but will
be performaned asynchronously, once the EventLoop reports the stream resource is
ready to accept data.
For this, it uses an in-memory buffer string to collect all outstanding writes.
This buffer has a soft-limit applied which defines how much data it is willing
to accept before the caller SHOULD stop sending further data.
It currently defaults to 64 KiB and can be controlled through the public
`$softLimit` property like this:

```php
$buffer = $stream->getBuffer();
$buffer->softLimit = 8192;
```

See also [`write()`](#write) for more details.

> BC note: This class was previously called `Stream`.
  The `Stream` class still exists for BC reasons and will be removed in future
  versions of this package.

## Usage
```php
    $loop = React\EventLoop\Factory::create();

    $source = new React\Stream\ReadableResourceStream(fopen('omg.txt', 'r'), $loop);
    $dest = new React\Stream\WritableResourceStream(fopen('wtf.txt', 'w'), $loop);

    $source->pipe($dest);

    $loop->run();
```

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/stream:^0.6
```

More details about version upgrades can be found in the [CHANGELOG](CHANGELOG.md).

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](http://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

## License

MIT, see [LICENSE file](LICENSE).
