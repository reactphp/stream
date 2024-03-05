<?php

namespace React\Tests\Stream;

use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Stream\DuplexResourceStream;
use React\Stream\WritableResourceStream;

/**
 * @group internet
 */
class FunctionalInternetTest extends TestCase
{
    /** @var LoopInterface */
    private static $loop;

    public static function setUpBeforeClass(): void
    {
        Loop::set(new StreamSelectLoop());
        self::$loop = Loop::get();
    }

    public function testUploadKilobytePlain()
    {
        $size = 1000;
        $stream = stream_socket_client('tcp://httpbin.org:80');

        $stream = new DuplexResourceStream($stream);

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('error', $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadBiggerBlockPlain()
    {
        $size = 50 * 1000;
        $stream = stream_socket_client('tcp://httpbin.org:80');

        $stream = new DuplexResourceStream($stream);

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('error', $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadKilobyteSecure()
    {
        $size = 1000;
        $stream = stream_socket_client('ssl://httpbin.org:443');

        $stream = new DuplexResourceStream($stream);

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('error', $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadBiggerBlockSecure()
    {
        // A few dozen kilobytes should be enough to verify this works.
        // Underlying buffer sizes are platform-specific, so let's increase this
        // a bit to trigger different behavior on Linux vs Mac OS X.
        $size = 136 * 1000;

        $stream = stream_socket_client('ssl://httpbin.org:443');

        // PHP < 7.1.4 (and PHP < 7.0.18) suffers from a bug when writing big
        // chunks of data over TLS streams at once.
        // We work around this by limiting the write chunk size to 8192 bytes
        // here to also support older PHP versions.
        // See https://github.com/reactphp/socket/issues/105
        $stream = new DuplexResourceStream(
            $stream,
            null,
            new WritableResourceStream($stream, null, 8192)
        );

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('error', $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream);

        $this->assertNotEquals('', $buffer);
    }

    private function awaitStreamClose(DuplexResourceStream $stream, float $timeout = 10.0)
    {
        $stream->on('close', function () {
            self::$loop->stop();
        });

        $that = $this;
        self::$loop->addTimer($timeout, function () use ($that) {
            self::$loop->stop();
            $that->fail('Timed out while waiting for stream to close');
        });

        self::$loop->run();
    }
}
