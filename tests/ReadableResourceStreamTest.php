<?php

namespace React\Tests\Stream;

use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use Clue\StreamFilter as Filter;

class ReadableResourceStreamTest extends TestCase
{
    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructor()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        new ReadableResourceStream($stream);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructorWithExcessiveMode()
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = tempnam(sys_get_temp_dir(), 'test');
        $stream = @fopen($name, 'r+eANYTHING');
        unlink($name);

        $loop = $this->createLoopMock();
        Loop::set($loop);
        $buffer = new ReadableResourceStream($stream);
        $buffer->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnInvalidStream()
    {
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $this->setExpectedException('InvalidArgumentException');
        new ReadableResourceStream(false);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStream()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM does not report fopen mode for STDOUT');
        }

        $loop = $this->createLoopMock();
        Loop::set($loop);

        $this->setExpectedException('InvalidArgumentException');
        new ReadableResourceStream(STDOUT);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStreamWithExcessiveMode()
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'weANYTHING');
        unlink($name);

        $loop = $this->createLoopMock();
        Loop::set($loop);
        $this->setExpectedException('InvalidArgumentException');
        new ReadableResourceStream($stream);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionIfStreamDoesNotSupportNonBlocking()
    {
        if (!in_array('blocking', stream_get_wrappers())) {
            stream_wrapper_register('blocking', 'React\Tests\Stream\EnforceBlockingWrapper');
        }

        $stream = fopen('blocking://test', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $this->setExpectedException('RuntimeException');
        new ReadableResourceStream($stream);
    }


    public function testCloseShouldEmitCloseEvent()
    {
        $stream = fopen('php://temp', 'r+');

        $conn = new ReadableResourceStream($stream);
        $conn->on('close', $this->expectCallableOnce());

        $conn->close();

        $this->assertFalse($conn->isReadable());
    }

    public function testCloseTwiceShouldEmitCloseEventOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->on('close', $this->expectCallableOnce());

        $conn->close();
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEvent()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $capturedData = null;

        $conn = new ReadableResourceStream($stream);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
        $this->assertSame("foobar\n", $capturedData);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkMatchingBufferSize()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $capturedData = null;

        $conn = new ReadableResourceStream($stream, 4321);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $conn->handleData($stream);

        $this->assertTrue($conn->isReadable());
        $this->assertEquals(4321, strlen($capturedData));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkUntilStreamEndsWhenBufferSizeIsInfinite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $capturedData = null;

        $conn = new ReadableResourceStream($stream, -1);

        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $conn->handleData($stream);

        $this->assertTrue($conn->isReadable());
        $this->assertEquals(100000, strlen($capturedData));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testEmptyStreamShouldNotEmitData()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->on('data', $this->expectCallableNever());

        $conn->handleData($stream);
    }

    public function testPipeShouldReturnDestination()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $this->assertSame($dest, $conn->pipe($dest));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testClosingStreamInDataEventShouldNotTriggerError()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::pause
     */
    public function testPauseRemovesReadStreamFromLoop()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->pause();
        $conn->pause();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::pause
     */
    public function testResumeDoesAddStreamToLoopOnlyOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->resume();
        $conn->resume();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testCloseRemovesReadStreamFromLoop()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testCloseAfterPauseRemovesReadStreamFromLoopOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->pause();
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testResumeAfterCloseDoesAddReadStreamToLoopOnlyOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->close();
        $conn->resume();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataFiltered()
    {
        $stream = fopen('php://temp', 'r+');

        // add a filter which removes every 'a' when reading
        Filter\append($stream, function ($chunk) {
            return str_replace('a', '', $chunk);
        }, STREAM_FILTER_READ);

        $loop = $this->createLoopMock();
        Loop::set($loop);

        $capturedData = null;

        $conn = new ReadableResourceStream($stream);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
        $this->assertSame("foobr\n", $capturedData);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataErrorShouldEmitErrorAndClose()
    {
        $stream = fopen('php://temp', 'r+');

        // add a filter which returns an error when encountering an 'a' when reading
        Filter\append($stream, function ($chunk) {
            if (strpos($chunk, 'a') !== false) {
                throw new \Exception('Invalid');
            }
            return $chunk;
        }, STREAM_FILTER_READ);

        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->on('data', $this->expectCallableNever());
        $conn->on('error', $this->expectCallableOnce());
        $conn->on('close', $this->expectCallableOnce());

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testEmptyReadShouldntFcloseStream()
    {
        list($stream, $_) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $loop = $this->createLoopMock();
        Loop::set($loop);

        $conn = new ReadableResourceStream($stream);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', $this->expectCallableNever());
        $conn->on('end', $this->expectCallableNever());

        $conn->handleData();

        fclose($stream);
        fclose($_);
    }

    private function createLoopMock()
    {
        return $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
    }
}
