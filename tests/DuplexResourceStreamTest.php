<?php

namespace React\Tests\Stream;

use React\Stream\DuplexResourceStream;
use Clue\StreamFilter as Filter;
use React\Stream\WritableResourceStream;

class DuplexResourceStreamTest extends TestCase
{
    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructor()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        new DuplexResourceStream($stream, $loop);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically()
    {
        $resource = fopen('php://temp', 'r+');

        $stream = new DuplexResourceStream($resource);

        $ref = new \ReflectionProperty($stream, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($stream);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructorWithExcessiveMode()
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = tempnam(sys_get_temp_dir(), 'test');
        $stream = @fopen($name, 'r+eANYTHING');
        unlink($name);

        $loop = $this->createLoopMock();
        $buffer = new DuplexResourceStream($stream, $loop);
        $buffer->close();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnInvalidStream()
    {
        $loop = $this->createLoopMock();

        $this->setExpectedException('InvalidArgumentException');
        new DuplexResourceStream('breakme', $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStream()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM does not report fopen mode for STDOUT');
        }

        $loop = $this->createLoopMock();

        $this->setExpectedException('InvalidArgumentException');
        new DuplexResourceStream(STDOUT, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStreamWithExcessiveMode()
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'weANYTHING');
        unlink($name);

        $loop = $this->createLoopMock();
        $this->setExpectedException('InvalidArgumentException');
        new DuplexResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionIfStreamDoesNotSupportNonBlocking()
    {
        if (!in_array('blocking', stream_get_wrappers())) {
            stream_wrapper_register('blocking', 'React\Tests\Stream\EnforceBlockingWrapper');
        }

        $stream = fopen('blocking://test', 'r+');
        $loop = $this->createLoopMock();

        $this->setExpectedException('RunTimeException');
        new DuplexResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructorAcceptsBuffer()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        new DuplexResourceStream($stream, $loop, null, $buffer);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionIfStreamDoesNotSupportNonBlockingWithBufferGiven()
    {
        if (!in_array('blocking', stream_get_wrappers())) {
            stream_wrapper_register('blocking', 'React\Tests\Stream\EnforceBlockingWrapper');
        }

        $stream = fopen('blocking://test', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $this->setExpectedException('RunTimeException');
        new DuplexResourceStream($stream, $loop, null, $buffer);
    }

    public function testCloseShouldEmitCloseEvent()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('close', $this->expectCallableOnce());
        $conn->on('end', $this->expectCallableNever());

        $conn->close();

        $this->assertFalse($conn->isReadable());
    }

    public function testEndShouldEndBuffer()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $buffer->expects($this->once())->method('end')->with('foo');

        $conn = new DuplexResourceStream($stream, $loop, null, $buffer);
        $conn->end('foo');
    }


    public function testEndAfterCloseIsNoOp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $buffer->expects($this->never())->method('end');

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
        $conn->end();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEvent()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');

        $this->assertSame("foobar\n", $capturedData);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkMatchingBufferSize()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop, 4321);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');


        $this->assertTrue($conn->isReadable());
        $this->assertEquals(4321, strlen($capturedData));
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkUntilStreamEndsWhenBufferSizeIsInfinite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop, -1);

        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');


        $this->assertTrue($conn->isReadable());
        $this->assertEquals(100000, strlen($capturedData));
    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testEmptyStreamShouldNotEmitData()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');

    }

    /**
     * @covers React\Stream\DuplexResourceStream::write
     */
    public function testWrite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->write("foo\n");

        rewind($stream);
        $this->assertSame("foo\n", fgets($stream));
    }

    /**
     * @covers React\Stream\DuplexResourceStream::end
     * @covers React\Stream\DuplexResourceStream::isReadable
     * @covers React\Stream\DuplexResourceStream::isWritable
     */
    public function testEnd()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->end();

        $this->assertFalse(is_resource($stream));
        $this->assertFalse($conn->isReadable());
        $this->assertFalse($conn->isWritable());
    }

    /**
     * @covers React\Stream\DuplexResourceStream::end
     */
    public function testEndRemovesReadStreamFromLoop()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->end('bye');
    }

    /**
     * @covers React\Stream\DuplexResourceStream::pause
     */
    public function testPauseRemovesReadStreamFromLoop()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->pause();
        $conn->pause();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::pause
     */
    public function testResumeDoesAddStreamToLoopOnlyOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->resume();
        $conn->resume();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::close
     */
    public function testCloseRemovesReadStreamFromLoop()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::close
     */
    public function testCloseAfterPauseRemovesReadStreamFromLoopOnlyOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->pause();
        $conn->close();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::close
     */
    public function testResumeAfterCloseDoesAddReadStreamToLoopOnlyOnce()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
        $conn->resume();
    }

    public function testEndedStreamsShouldNotWrite()
    {
        $file = tempnam(sys_get_temp_dir(), 'reactphptest_');
        $stream = fopen($file, 'r+');
        $loop = $this->createWriteableLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->write("foo\n");
        $conn->end();

        $res = $conn->write("bar\n");
        $stream = fopen($file, 'r');

        $this->assertSame("foo\n", fgets($stream));
        $this->assertFalse($res);

        unlink($file);
    }

    public function testPipeShouldReturnDestination()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $this->assertSame($dest, $conn->pipe($dest));
    }

    public function testBufferEventsShouldBubbleUp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $conn = new DuplexResourceStream($stream, $loop, null, $buffer);

        $conn->on('drain', $this->expectCallableOnce());
        $conn->on('error', $this->expectCallableOnce());

        $buffer->emit('drain');
        $buffer->emit('error', [new \RuntimeException('Whoops')]);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testClosingStreamInDataEventShouldNotTriggerError()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');

    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataFiltered()
    {
        $stream = fopen('php://temp', 'r+');

        // add a filter which removes every 'a' when reading
        Filter\append($stream, function ($chunk) {
            return str_replace('a', '', $chunk);
        }, STREAM_FILTER_READ);

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');

        $this->assertSame("foobr\n", $capturedData);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
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

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());
        $conn->on('error', $this->expectCallableOnce());
        $conn->on('close', $this->expectCallableOnce());

        fwrite($stream, "foobar\n");
        rewind($stream);

        $ref = new \ReflectionMethod($conn, 'handleData');
        $ref->setAccessible(true);
        $ref->invoke($conn, 'handleData');

    }

    private function createWriteableLoopMock()
    {
        $loop = $this->createLoopMock();
        $loop
            ->expects($this->once())
            ->method('addWriteStream')
            ->will($this->returnCallback(function ($stream, $listener) {
                call_user_func($listener, $stream);
            }));

        return $loop;
    }

    private function createLoopMock()
    {
        return $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
    }
}
