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

* `isWritable()`: Check if the stream can still be written to. It cannot be
  written to after an error or when it is closed.
* `write($data)`: Write some data into the stream. If the stream cannot handle
  it, it should buffer the data or emit and `error` event. If the internal
  buffer is full after adding `$data`, `write` should return false, indicating
  that the caller should stop sending data until the buffer `drain`s.
* `end($data = null)`: Optionally write some final data to the stream, empty
  the buffer, then close it.

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
