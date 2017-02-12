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

* `data`: Emitted whenever data was read from the source.
* `end`: Emitted when the source has reached the `eof`.
* `error`: Emitted when an error occurs.
* `close`: Emitted when the connection is closed.

### Methods

* `isReadable()`: Check if the stream is still in a state allowing it to be
  read from. It becomes unreadable when the connection ends, closes or an
  error occurs.
* `pause()`: Remove the data source file descriptor from the event loop. This
  allows you to throttle incoming data.
* `resume()`: Re-attach the data source after a `pause()`.
* `pipe(WritableStreamInterface $dest, array $options = [])`: Pipe this
  readable stream into a writable stream. Automatically sends all incoming
  data to the destination. Automatically throttles based on what the
  destination can handle.

## Writable Streams

### EventEmitter Events

* `drain`: Emitted if the write buffer became full previously and is now ready
  to accept more data.
* `error`: Emitted whenever an error occurs.
* `close`: Emitted whenever the connection is closed.
* `pipe`: Emitted whenever a readable stream is `pipe()`d into this stream.

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

    $sourceFile = fopen('omg.txt', 'r');
    stream_set_blocking($sourceFile, 0);
    $source = new React\Stream\Stream($sourceFile, $loop);
    
    $destFile = fopen('wtf.txt', 'w');
    stream_set_blocking($destFile, 0);
    $dest = new React\Stream\Stream($destFile, $loop);

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
