<?php

namespace React\Tests\Stream;

use React\Stream\Stream;
use Clue\StreamFilter as Filter;

class StreamTest extends TestCase
{
    /**
     * @covers React\Stream\Stream::__construct
     */
    public function testConstructor()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
    }

    /**
     * @covers React\Stream\Stream::__construct
     */
    public function testConstructorThrowsExceptionOnInvalidStream()
    {
        $this->setExpectedException('InvalidArgumentException');
        $loop = $this->createLoopMock();
        $conn = new Stream('breakme', $loop);
    }

    /**
     * @covers React\Stream\Stream::__construct
     */
    public function testConstructorAcceptsBuffer()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $conn = new Stream($stream, $loop, $buffer);

        $this->assertSame($buffer, $conn->getBuffer());
    }

    public function testCloseShouldEmitCloseEvent()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
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

        $conn = new Stream($stream, $loop, $buffer);
        $conn->end('foo');
    }


    public function testEndAfterCloseIsNoOp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $buffer->expects($this->never())->method('end');

        $conn = new Stream($stream, $loop);
        $conn->close();
        $conn->end();
    }

    /**
     * @covers React\Stream\Stream::__construct
     * @covers React\Stream\Stream::handleData
     */
    public function testDataEvent()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new Stream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
        $this->assertSame("foobar\n", $capturedData);
    }

    /**
     * @covers React\Stream\Stream::__construct
     * @covers React\Stream\Stream::handleData
     */
    public function testDataEventDoesEmitOneChunkMatchingBufferSize()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new Stream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $conn->handleData($stream);

        $this->assertTrue($conn->isReadable());
        $this->assertEquals($conn->bufferSize, strlen($capturedData));
    }

    /**
     * @covers React\Stream\Stream::__construct
     * @covers React\Stream\Stream::handleData
     */
    public function testDataEventDoesEmitOneChunkUntilStreamEndsWhenBufferSizeIsInfinite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new Stream($stream, $loop);
        $conn->bufferSize = null;

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
     * @covers React\Stream\Stream::handleData
     */
    public function testEmptyStreamShouldNotEmitData()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\Stream::write
     */
    public function testWrite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $conn = new Stream($stream, $loop);
        $conn->write("foo\n");

        rewind($stream);
        $this->assertSame("foo\n", fgets($stream));
    }

    /**
     * @covers React\Stream\Stream::end
     * @covers React\Stream\Stream::isReadable
     * @covers React\Stream\Stream::isWritable
     */
    public function testEnd()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
        $conn->end();

        $this->assertFalse(is_resource($stream));
        $this->assertFalse($conn->isReadable());
        $this->assertFalse($conn->isWritable());
    }

    public function testEndedStreamsShouldNotWrite()
    {
        $file = tempnam(sys_get_temp_dir(), 'reactphptest_');
        $stream = fopen($file, 'r+');
        $loop = $this->createWriteableLoopMock();

        $conn = new Stream($stream, $loop);
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

        $conn = new Stream($stream, $loop);
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();

        $this->assertSame($dest, $conn->pipe($dest));
    }

    public function testBufferEventsShouldBubbleUp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);

        $conn->on('drain', $this->expectCallableOnce());
        $conn->on('error', $this->expectCallableOnce());

        $buffer = $conn->getBuffer();
        $buffer->emit('drain');
        $buffer->emit('error', array(new \RuntimeException('Whoops')));
    }

    /**
     * @covers React\Stream\Stream::handleData
     */
    public function testClosingStreamInDataEventShouldNotTriggerError()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\Stream::handleData
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

        $conn = new Stream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
        $this->assertSame("foobr\n", $capturedData);
    }

    /**
     * @covers React\Stream\Stream::handleData
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

        $conn = new Stream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());
        $conn->on('error', $this->expectCallableOnce());
        $conn->on('close', $this->expectCallableOnce());

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
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
