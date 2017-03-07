<?php

namespace React\Stream;

/**
 * The `DuplexStreamInterface` is responsible for providing an interface for
 * duplex streams (both readable and writable).
 *
 * It builds on top of the existing interfaces for readable and writable streams
 * and follows the exact same method and event semantics.
 * If you're new to this concept, you should look into the
 * `ReadableStreamInterface` and `WritableStreamInterface` first.
 *
 * Besides defining a few methods, this interface also implements the
 * `EventEmitterInterface` which allows you to react to the same events defined
 * on the `ReadbleStreamInterface` and `WritableStreamInterface`.
 *
 * @see ReadableStreamInterface
 * @see WritableStreamInterface
 */
interface DuplexStreamInterface extends ReadableStreamInterface, WritableStreamInterface
{
}
