# Stream Component

[![Build Status](https://secure.travis-ci.org/reactphp/stream.png?branch=master)](http://travis-ci.org/reactphp/stream)

Basic readable and writable stream interfaces that support piping.

In order to make the event loop easier to use, this component introduces the
concept of streams. They are very similar to the streams found in PHP itself,
but have an interface more suited for async I/O.

Mainly it provides interfaces for readable and writable streams, plus a file
descriptor based implementation with an in-memory write buffer.

This component depends on `événement`, which is an implementation of the
`EventEmitter`.

## Readable Streams

### EventEmitter Events

* `data`: Emitted whenever data was read from the source
  with a single mixed argument for incoming data.
* `end`: Emitted when the source has successfully reached the end
  of the stream (EOF).
  This event will only be emitted if the *end* was reached successfully, not
  if the stream was interrupted due to an error or explicitly closed.
  Also note that not all streams know the concept of a "successful end".
* `error`: Emitted when an error occurs
  with a single `Exception` argument for error instance.
* `close`: Emitted when the stream is closed.

### Methods

* `isReadable()`: Check if the stream is still in a state allowing it to be
  read from. It becomes unreadable when the stream ends, closes or an
  error occurs.
* `pause()`: Remove the data source file descriptor from the event loop. This
  allows you to throttle incoming data.
* `resume()`: Re-attach the data source after a `pause()`.
* `pipe(WritableStreamInterface $dest, array $options = [])`:
Pipe all the data from this readable source into the given writable destination.

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

## Writable Streams

### EventEmitter Events

* `drain`: Emitted if the write buffer became full previously and is now ready
  to accept more data.
* `error`: Emitted whenever an error occurs
  with a single `Exception` argument for error instance.
* `close`: Emitted whenever the stream is closed.
* `pipe`: Emitted whenever a readable stream is `pipe()`d into this stream
  with a single `ReadableStreamInterface` argument for source stream.

### Methods

* `isWritable()`:
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

* `write($data)`:
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
data until the buffer `drain`s.

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

* `end($data = null)`:
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

Note that this method should not be confused with the `close()` method.

## Usage
```php
    $loop = React\EventLoop\Factory::create();

    $source = new React\Stream\Stream(fopen('omg.txt', 'r'), $loop);
    $dest = new React\Stream\Stream(fopen('wtf.txt', 'w'), $loop);

    $source->pipe($dest);

    $loop->run();
```

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require react/stream:^0.4.6
```

More details about version upgrades can be found in the [CHANGELOG](CHANGELOG.md).
