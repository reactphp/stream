<?php

namespace React\Tests\Stream;

use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableStreamInterface;
use function Clue\StreamFilter\append as filter_append;

class ReadableResourceStreamTest extends TestCase
{
    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructor(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        new ReadableResourceStream($stream, $loop);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert(is_resource($resource));

        $stream = new ReadableResourceStream($resource);

        $ref = new \ReflectionProperty($stream, 'loop');
        $ref->setAccessible(true);
        $loop = $ref->getValue($stream);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
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
        $buffer = new ReadableResourceStream($stream, $loop);
        $buffer->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnInvalidStream(): void
    {
        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new ReadableResourceStream(false, $loop); // @phpstan-ignore-line
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnWriteOnlyStream(): void
    {
        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new ReadableResourceStream(STDOUT, $loop);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
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
        new ReadableResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
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
        new ReadableResourceStream($stream, $loop);
    }

    public function testCloseShouldEmitCloseEvent(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('close', $this->expectCallableOnce());

        $conn->close();

        $this->assertFalse($conn->isReadable());
    }

    public function testCloseTwiceShouldEmitCloseEventOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('close', $this->expectCallableOnce());

        $conn->close();
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEvent(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData();
        $this->assertSame("foobar\n", $capturedData);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkMatchingBufferSize(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new ReadableResourceStream($stream, $loop, 4321);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $conn->handleData();

        $this->assertTrue($conn->isReadable());
        $this->assertEquals(4321, strlen($capturedData));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::__construct
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testDataEventDoesEmitOneChunkUntilStreamEndsWhenBufferSizeIsInfinite(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $capturedData = null;

        $conn = new ReadableResourceStream($stream, $loop, -1);

        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, str_repeat("a", 100000));
        rewind($stream);

        $conn->handleData();

        $this->assertTrue($conn->isReadable());
        $this->assertEquals(100000, strlen($capturedData));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testEmptyStreamShouldNotEmitData(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());

        $conn->handleData();
    }

    public function testPipeShouldReturnDestination(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $dest = $this->createMock(WritableStreamInterface::class);
        assert($dest instanceof WritableStreamInterface);

        $this->assertSame($dest, $conn->pipe($dest));
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testClosingStreamInDataEventShouldNotTriggerError(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', function ($data) use ($conn) {
            $conn->close();
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::pause
     */
    public function testPauseRemovesReadStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->pause();
        $conn->pause();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::pause
     */
    public function testResumeDoesAddStreamToLoopOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->resume();
        $conn->resume();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testCloseRemovesReadStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testCloseAfterPauseRemovesReadStreamFromLoopOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->pause();
        $conn->close();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::close
     */
    public function testResumeAfterCloseDoesAddReadStreamToLoopOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream);

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->close();
        $conn->resume();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
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

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('data', function ($data) use (&$capturedData) {
            $capturedData = $data;
        });

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData();
        $this->assertSame("foobr\n", $capturedData);
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
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

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('data', $this->expectCallableNever());
        $conn->on('error', $this->expectCallableOnce());
        $conn->on('close', $this->expectCallableOnce());

        fwrite($stream, "foobar\n");
        rewind($stream);

        $conn->handleData();
    }

    /**
     * @covers React\Stream\ReadableResourceStream::handleData
     */
    public function testEmptyReadShouldntFcloseStream(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        assert(is_array($pair));
        [$stream, $_] = $pair;

        $loop = $this->createLoopMock();

        $conn = new ReadableResourceStream($stream, $loop);
        $conn->on('error', $this->expectCallableNever());
        $conn->on('data', $this->expectCallableNever());
        $conn->on('end', $this->expectCallableNever());

        $conn->handleData();

        fclose($stream);
        fclose($_);
    }

    /** @return MockObject&LoopInterface */
    private function createLoopMock(): MockObject
    {
        /** @var MockObject&LoopInterface */
        return $this->createMock(LoopInterface::class);
    }
}
