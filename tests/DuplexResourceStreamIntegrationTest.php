<?php

namespace React\Tests\Stream;

use Clue\StreamFilter as Filter;
use React\EventLoop\Loop;
use React\Stream\DuplexResourceStream;
use React\Stream\ReadableResourceStream;
use React\EventLoop\ExtEventLoop;
use React\EventLoop\ExtLibeventLoop;
use React\EventLoop\ExtLibevLoop;
use React\EventLoop\LoopInterface;
use React\EventLoop\LibEventLoop;
use React\EventLoop\LibEvLoop;
use React\EventLoop\StreamSelectLoop;

class DuplexResourceStreamIntegrationTest extends TestCase
{
    public function loopProvider()
    {
        return array(
            array(
                function() {
                    return true;
                },
                function () {
                    Loop::set(new StreamSelectLoop());
                    return Loop::get();
                }
            ),
            array(
                function () {
                    return function_exists('event_base_new');
                },
                function () {
                    Loop::set(
                        class_exists('React\EventLoop\ExtLibeventLoop') ? new ExtLibeventLoop() : new LibEventLoop()
                    );
                    return Loop::get();
                }
            ),
            array(
                function () {
                    return class_exists('libev\EventLoop');
                },
                function () {
                    Loop::set(
                        class_exists('React\EventLoop\ExtLibeventLoop') ? new ExtLibeventLoop() : new LibEventLoop()
                    );
                    return Loop::get();
                }
            ),
            array(
                function () {
                    return class_exists('EventBase') && class_exists('React\EventLoop\ExtEventLoop');
                },
                function () {
                    Loop::set(new ExtEventLoop());
                    return Loop::get();
                }
            )
        );
    }

    /**
     * @dataProvider loopProvider
     */
    public function testBufferReadsLargeChunks($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $bufferSize = 4096;
        $streamA = new DuplexResourceStream($sockA, $bufferSize);
        $streamB = new DuplexResourceStream($sockB, $bufferSize);

        $testString = str_repeat("*", $bufferSize + 1);

        $buffer = "";
        $streamB->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });

        $streamA->write($testString);

        $this->loopTick(Loop::get());
        $this->loopTick(Loop::get());
        $this->loopTick(Loop::get());

        $streamA->close();
        $streamB->close();

        $this->assertEquals($testString, $buffer);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testWriteLargeChunk($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA);
        $streamB = new DuplexResourceStream($sockB);

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

        Loop::get()->run();

        $streamA->close();
        $streamB->close();

        $this->assertEquals($size, $received);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotEmitDataIfNothingHasBeenWritten($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA);
        $streamB = new DuplexResourceStream($sockB);

        // end streamA without writing any data
        $streamA->end();

        // streamB should not emit any data
        $streamB->on('data', $this->expectCallableNever());

        Loop::get()->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfRemoteSideFromPairHasBeenClosed($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        list($sockA, $sockB) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $streamA = new DuplexResourceStream($sockA);
        $streamB = new DuplexResourceStream($sockB);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        Loop::get()->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfServerSideHasBeenClosed($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $server = stream_socket_server('tcp://127.0.0.1:0');

        $client = stream_socket_client(stream_socket_get_name($server, false));
        $peer = stream_socket_accept($server);

        $streamA = new DuplexResourceStream($client);
        $streamB = new DuplexResourceStream($peer);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        Loop::get()->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testDoesNotWriteDataIfClientSideHasBeenClosed($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $server = stream_socket_server('tcp://127.0.0.1:0');

        $client = stream_socket_client(stream_socket_get_name($server, false));
        $peer = stream_socket_accept($server);

        $streamA = new DuplexResourceStream($peer);
        $streamB = new DuplexResourceStream($client);

        // end streamA without writing any data
        $streamA->pause();
        $streamA->write('hello');
        $streamA->on('close', $this->expectCallableOnce());

        $streamB->on('data', $this->expectCallableNever());
        $streamB->close();

        Loop::get()->run();

        $streamA->close();
        $streamB->close();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsSingleChunkFromProcessPipe($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $stream = new ReadableResourceStream(popen('echo test', 'r'));
        $stream->on('data', $this->expectCallableOnceWith("test\n"));
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        Loop::get()->run();
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsMultipleChunksFromProcessPipe($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $stream = new ReadableResourceStream(popen('echo a;sleep 0.1;echo b;sleep 0.1;echo c', 'r'));

        $buffer = '';
        $stream->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        Loop::get()->run();

        $this->assertEquals("a\n" . "b\n" . "c\n", $buffer);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsLongChunksFromProcessPipe($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $stream = new ReadableResourceStream(popen('dd if=/dev/zero bs=12345 count=1234 2>&-', 'r'));

        $bytes = 0;
        $stream->on('data', function ($chunk) use (&$bytes) {
            $bytes += strlen($chunk);
        });

        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        Loop::get()->run();

        $this->assertEquals(12345 * 1234, $bytes);
    }

    /**
     * @dataProvider loopProvider
     */
    public function testReadsNothingFromProcessPipeWithNoOutput($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $loopFactory();

        $stream = new ReadableResourceStream(popen('true', 'r'));
        $stream->on('data', $this->expectCallableNever());
        $stream->on('end', $this->expectCallableOnce());
        $stream->on('error', $this->expectCallableNever());

        Loop::get()->run();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     * @dataProvider loopProvider
     */
    public function testEmptyReadShouldntFcloseStream($condition, $loopFactory)
    {
        if (true !== $condition()) {
            return $this->markTestSkipped('Loop implementation not available');
        }

        $server = stream_socket_server('tcp://127.0.0.1:0');

        $client = stream_socket_client(stream_socket_get_name($server, false));
        $stream = stream_socket_accept($server);


        // add a filter which returns an error when encountering an 'a' when reading
        Filter\append($stream, function ($chunk) {
            return '';
        }, STREAM_FILTER_READ);

        $loopFactory();

        $conn = new DuplexResourceStream($stream);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', $this->expectCallableNever());
        $conn->on('end', $this->expectCallableNever());

        fwrite($client, "foobar\n");

        $conn->handleData($stream);

        fclose($stream);
        fclose($client);
        fclose($server);
    }

    private function loopTick(LoopInterface $loop)
    {
        $loop->addTimer(0, function () use ($loop) {
            $loop->stop();
        });
        $loop->run();
    }
}
