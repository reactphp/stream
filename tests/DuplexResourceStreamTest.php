<?php

namespace React\Tests\Stream;

use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;
use function Clue\StreamFilter\append as filter_append;

class DuplexResourceStreamTest extends TestCase
{
    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructor(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        new DuplexResourceStream($stream, $loop);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert(is_resource($resource));

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
    public function testConstructorWithExcessiveMode(): void
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = (string) tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'r+eANYTHING');
        assert(is_resource($stream));
        unlink($name);

        $loop = $this->createLoopMock();
        $buffer = new DuplexResourceStream($stream, $loop);
        $buffer->close();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnInvalidStream(): void
    {
        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new DuplexResourceStream('breakme', $loop); // @phpstan-ignore-line
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStream(): void
    {
        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new DuplexResourceStream(STDOUT, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStreamWithExcessiveMode(): void
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = (string) tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'weANYTHING');
        assert(is_resource($stream));
        unlink($name);

        $loop = $this->createLoopMock();
        $this->expectException(\InvalidArgumentException::class);
        new DuplexResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionIfStreamDoesNotSupportNonBlocking(): void
    {
        if (!in_array('blocking', stream_get_wrappers())) {
            stream_wrapper_register('blocking', 'React\Tests\Stream\EnforceBlockingWrapper');
        }

        $stream = fopen('blocking://test', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $this->expectException(\RuntimeException::class);
        new DuplexResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructorAcceptsBuffer(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = $this->createMock(WritableStreamInterface::class);
        assert($buffer instanceof WritableStreamInterface);

        new DuplexResourceStream($stream, $loop, null, $buffer);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     */
    public function testConstructorThrowsExceptionIfStreamDoesNotSupportNonBlockingWithBufferGiven(): void
    {
        if (!in_array('blocking', stream_get_wrappers())) {
            stream_wrapper_register('blocking', 'React\Tests\Stream\EnforceBlockingWrapper');
        }

        $stream = fopen('blocking://test', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        assert($buffer instanceof WritableStreamInterface);

        $this->expectException(\RuntimeException::class);
        new DuplexResourceStream($stream, $loop, null, $buffer);
    }

    public function testCloseShouldEmitCloseEvent(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('close', $this->expectCallableOnce());
        $conn->on('end', $this->expectCallableNever());

        $conn->close();

        $this->assertFalse($conn->isReadable());
    }

    public function testEndShouldEndBuffer(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = $this->createMock(WritableStreamInterface::class);
        $buffer->expects($this->once())->method('end')->with('foo');
        assert($buffer instanceof WritableStreamInterface);

        $conn = new DuplexResourceStream($stream, $loop, null, $buffer);
        $conn->end('foo');
    }


    public function testEndAfterCloseIsNoOp(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = $this->createMock(WritableStreamInterface::class);
        $buffer->expects($this->never())->method('end');
        assert($buffer instanceof WritableStreamInterface);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
        $conn->end();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEvent(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
        $this->assertSame("foobar\n", $capturedData);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkMatchingBufferSize(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop, 4321);
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
     * @covers React\Stream\DuplexResourceStream::__construct
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkUntilStreamEndsWhenBufferSizeIsInfinite(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new DuplexResourceStream($stream, $loop, -1);

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
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testEmptyStreamShouldNotEmitData(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::write
     */
    public function testWrite(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

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
    public function testEnd(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

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
    public function testEndRemovesReadStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->end('bye');
    }

    /**
     * @covers React\Stream\DuplexResourceStream::pause
     */
    public function testPauseRemovesReadStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

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
    public function testResumeDoesAddStreamToLoopOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->resume();
        $conn->resume();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::close
     */
    public function testCloseRemovesReadStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
    }

    /**
     * @covers React\Stream\DuplexResourceStream::close
     */
    public function testCloseAfterPauseRemovesReadStreamFromLoopOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

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
    public function testResumeAfterCloseDoesAddReadStreamToLoopOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->close();
        $conn->resume();
    }

    public function testEndedStreamsShouldNotWrite(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'reactphptest_');
        $stream = fopen($file, 'r+');
        assert(is_resource($stream));

        $loop = $this->createWriteableLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->write("foo\n");
        $conn->end();

        $res = $conn->write("bar\n");

        $stream = fopen($file, 'r');
        assert(is_resource($stream));

        $this->assertSame("foo\n", fgets($stream));
        $this->assertFalse($res);

        unlink($file);
    }

    public function testPipeShouldReturnDestination(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $dest = $this->createMock(WritableStreamInterface::class);
        assert($dest instanceof WritableStreamInterface);

        $this->assertSame($dest, $conn->pipe($dest));
    }

    public function testBufferEventsShouldBubbleUp(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

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
    public function testClosingStreamInDataEventShouldNotTriggerError(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new DuplexResourceStream($stream, $loop);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData($stream);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataFiltered(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        // add a filter which removes every 'a' when reading
        filter_append($stream, function ($chunk) {
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

        $conn->handleData($stream);
        $this->assertSame("foobr\n", $capturedData);
    }

    /**
     * @covers React\Stream\DuplexResourceStream::handleData
     */
    public function testDataErrorShouldEmitErrorAndClose(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        // add a filter which returns an error when encountering an 'a' when reading
        filter_append($stream, function ($chunk) {
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

        $conn->handleData($stream);
    }

    private function createWriteableLoopMock(): LoopInterface
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

    /** @return LoopInterface&MockObject */
    private function createLoopMock(): MockObject
    {
        /** @var MockObject&LoopInterface */
        return $this->createMock(LoopInterface::class);
    }
}
