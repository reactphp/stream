<?php

namespace React\Tests\Stream;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use React\Stream\WritableResourceStream;
use React\Stream\Event;

/**
 * @group internet
 */
class FunctionalInternetTest extends TestCase
{
    public function testUploadKilobytePlain()
    {
        $size = 1000;
        $stream = stream_socket_client('tcp://httpbin.org:80');

        $loop = Factory::create();
        $stream = new DuplexResourceStream($stream, $loop);

        $buffer = '';
        $stream->on(Event\DATA, function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on(Event\ERROR, $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream, $loop);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadBiggerBlockPlain()
    {
        $size = 50 * 1000;
        $stream = stream_socket_client('tcp://httpbin.org:80');

        $loop = Factory::create();
        $stream = new DuplexResourceStream($stream, $loop);

        $buffer = '';
        $stream->on(Event\DATA, function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on(Event\ERROR, $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream, $loop);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadKilobyteSecure()
    {
        $size = 1000;
        $stream = stream_socket_client('tls://httpbin.org:443');

        $loop = Factory::create();
        $stream = new DuplexResourceStream($stream, $loop);

        $buffer = '';
        $stream->on(Event\DATA, function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on(Event\ERROR, $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream, $loop);

        $this->assertNotEquals('', $buffer);
    }

    public function testUploadBiggerBlockSecureRequiresSmallerChunkSize()
    {
        $size = 50 * 1000;
        $stream = stream_socket_client('tls://httpbin.org:443');

        $loop = Factory::create();
        $stream = new DuplexResourceStream(
            $stream,
            $loop,
            null,
            new WritableResourceStream($stream, $loop, null, 8192)
        );

        $buffer = '';
        $stream->on(Event\DATA, function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on(Event\ERROR, $this->expectCallableNever());

        $stream->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . str_repeat('.', $size));

        $this->awaitStreamClose($stream, $loop);

        $this->assertNotEquals('', $buffer);
    }

    private function awaitStreamClose(DuplexResourceStream $stream, LoopInterface $loop, $timeout = 10.0)
    {
        $stream->on(Event\CLOSE, function () use ($loop) {
            $loop->stop();
        });

        $that = $this;
        $loop->addTimer($timeout, function () use ($loop, $that) {
            $loop->stop();
            $that->fail('Timed out while waiting for stream to close');
        });

        $loop->run();
    }
}
