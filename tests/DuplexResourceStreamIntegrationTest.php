<?php

namespace React\Tests\Stream;

use React\Stream\DuplexResourceStream;
use React\Stream\ReadableResourceStream;
use React\EventLoop\ExtEventLoop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use function Clue\StreamFilter\append as filter_append;

class DuplexResourceStreamIntegrationTest extends TestCase
{
    public function loopProvider(): \Generator
    {
        yield [
            function() {
                return true;
            },
            function () {
                return new StreamSelectLoop();
            }
        ];
        yield [
            function () {
                return class_exists('EventBase');
            },
            function () {
                return new ExtEventLoop();
            }
        ];
    }

    /**
     * @dataProvider loopProvider
     */
    public function testBufferReadsLargeChunks(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert(is_array($pair));
        [$sockA, $sockB] = $pair;

        $bufferSize = 4096;
        $streamA = new DuplexResourceStream($sockA, $loop, $bufferSize);
        $streamB = new DuplexResourceStream($sockB, $loop, $bufferSize);

        $testString = str_repeat("*", $bufferSize + 1);

        $buffer = "";
        $streamB->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });

        $streamA->write($testString);

        $this->loopTick($loop);
        $this->loopTick($loop);
        $this->loopTick($loop);

        $streamA->close();
        $streamB->close();

        $this->assertEquals($testString, $buffer);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testWriteLargeChunk(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert(is_array($pair));
        [$sockA, $sockB] = $pair;

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);

        // limit seems to be 192 KiB
        $size = 256 * 1024;

        // sending side sends and expects clean close with no errors
        $streamA->end(str_repeat('*', $size));
        $streamA->on('close', $this->expectCallableOnce());
        $streamA->on('error', $this->expectCallableNever());

        // receiving side counts bytes and expects clean close with no errors
        $received = 0;
        $streamB->on('data', function ($chunk) use (&$received) {
            $received += strlen($chunk);
        });
        $streamB->on('close', $this->expectCallableOnce());
        $streamB->on('error', $this->expectCallableNever());

        $loop->run();

        $streamA->close();
        $streamB->close();

        $this->assertEquals($size, $received);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotEmitDataIfNothingHasBeenWritten(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert(is_array($pair));
        [$sockA, $sockB] = $pair;

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);

        // end streamA without writing any data
        $streamA->end();

        // streamB should not emit any data
        $streamB->on('data', $this->expectCallableNever());

        $loop->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfRemoteSideFromPairHasBeenClosed(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert(is_array($pair));
        [$sockA, $sockB] = $pair;

        $streamA = new DuplexResourceStream($sockA, $loop);
        $streamB = new DuplexResourceStream($sockB, $loop);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        $loop->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfServerSideHasBeenClosed(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $server = stream_socket_server('tcp://127.0.0.1:0');
        assert(is_resource($server));

        $client = stream_socket_client((string) stream_socket_get_name($server, false));
        assert(is_resource($client));

        $peer = stream_socket_accept($server);
        assert(is_resource($peer));

        $streamA = new DuplexResourceStream($client, $loop);
        $streamB = new DuplexResourceStream($peer, $loop);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        $loop->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfClientSideHasBeenClosed(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $server = stream_socket_server('tcp://127.0.0.1:0');
        assert(is_resource($server));

        $client = stream_socket_client((string) stream_socket_get_name($server, false));
        assert(is_resource($client));

        $peer = stream_socket_accept($server);
        assert(is_resource($peer));

        $streamA = new DuplexResourceStream($peer, $loop);
        $streamB = new DuplexResourceStream($client, $loop);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        $loop->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsSingleChunkFromProcessPipe(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $fh = popen('echo test', 'r');
        assert(is_resource($fh));

        $stream = new ReadableResourceStream($fh, $loop);
        $stream->on('data', $this->expectCallableOnceWith("test\n"));
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $loop->run();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsMultipleChunksFromProcessPipe(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $fh = popen('echo a;sleep 0.1;echo b;sleep 0.1;echo c', 'r');
        assert(is_resource($fh));

        $stream = new ReadableResourceStream($fh, $loop);

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $loop->run();

        $this->assertEquals("a\n" . "b\n" . "c\n", $buffer);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsLongChunksFromProcessPipe(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $fh = popen('dd if=/dev/zero bs=12345 count=1234 2>&-', 'r');
        assert(is_resource($fh));

        $stream = new ReadableResourceStream($fh, $loop);

        $bytes = 0;
        $stream->on('data', function ($chunk) use (&$bytes) {
            $bytes += strlen($chunk);
        });

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $loop->run();

        $this->assertEquals(12345 * 1234, $bytes);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsNothingFromProcessPipeWithNoOutput(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $loop = $loopFactory();

        $fh = popen('true', 'r');
        assert(is_resource($fh));

        $stream = new ReadableResourceStream($fh, $loop);
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        $loop->run();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     * @dataProvider loopProvider
     */
    public function testEmptyReadShouldntFcloseStream(callable $condition, callable $loopFactory): void
    {
        if (true !== $condition()) {
            $this->markTestSkipped('Loop implementation not available');
        }

        $server = stream_socket_server('tcp://127.0.0.1:0');
        assert(is_resource($server));

        $client = stream_socket_client((string) stream_socket_get_name($server, false));
        assert(is_resource($client));

        $stream = stream_socket_accept($server);
        assert(is_resource($stream));


        // add a filter which returns an error when encountering an 'a' when reading
        filter_append($stream, function ($chunk) {
            return '';
        }, STREAM_FILTER_READ);

        $loop = $loopFactory();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', $this->expectCallableNever());
        $conn->on('end', $this->expectCallableNever());

        fwrite($client, "foobar\n");

        $conn->handleData($stream);

        fclose($stream);
        fclose($client);
        fclose($server);
    }

    private function loopTick(LoopInterface $loop): void
    {
        $loop->addTimer(0, function () use ($loop) {
            $loop->stop();
        });
        $loop->run();
    }
}
