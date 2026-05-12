<?php

namespace React\Tests\Stream;

use PHPUnit\Framework\MockObject\MockObject;
use React\EventLoop\LoopInterface;
use React\Stream\WritableResourceStream;
use function Clue\StreamFilter\append as filter_append;

class WritableResourceStreamTest extends TestCase
{
    /**
     * @covers React\Stream\WritableResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructor(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        new WritableResourceStream($stream, $loop);
    }

    public function testConstructWithoutLoopAssignsLoopAutomatically(): void
    {
        $resource = fopen('php://temp', 'r+');
        assert(is_resource($resource));

        $stream = new WritableResourceStream($resource);

        $ref = new \ReflectionProperty($stream, 'loop');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $loop = $ref->getValue($stream);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $loop);
    }

    /**
     * @covers React\Stream\WritableResourceStream::__construct
     * @doesNotPerformAssertions
     */
    public function testConstructorWithExcessiveMode(): void
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = (string) tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'w+eANYTHING');
        assert(is_resource($stream));
        unlink($name);

        $loop = $this->createLoopMock();
        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->close();
    }

    /**
     * @covers React\Stream\WritableResourceStream::__construct
     */
    public function testConstructorThrowsIfNotAValidStreamResource(): void
    {
        $stream = null;
        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new WritableResourceStream($stream, $loop); // @phpstan-ignore-line
    }

    /**
     * @covers React\Stream\WritableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnReadOnlyStream(): void
    {
        $stream = fopen('php://temp', 'r');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $this->expectException(\InvalidArgumentException::class);
        new WritableResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\WritableResourceStream::__construct
     */
    public function testConstructorThrowsExceptionOnReadOnlyStreamWithExcessiveMode(): void
    {
        // excessive flags are ignored for temp streams, so we have to use a file stream
        $name = (string) tempnam(sys_get_temp_dir(), 'test');
        $stream = fopen($name, 'reANYTHING');
        assert(is_resource($stream));
        unlink($name);

        $loop = $this->createLoopMock();
        $this->expectException(\InvalidArgumentException::class);
        new WritableResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\WritableResourceStream::__construct
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
        new WritableResourceStream($stream, $loop);
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testWrite(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createWriteableLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());

        $buffer->write("foobar\n");
        rewind($stream);
        $this->assertSame("foobar\n", fread($stream, 1024));
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     */
    public function testWriteWithDataDoesAddResourceToLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('addWriteStream')->with($this->equalTo($stream));

        $buffer = new WritableResourceStream($stream, $loop);

        $buffer->write("foobar\n");
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testEmptyWriteDoesNotAddToLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->never())->method('addWriteStream');

        $buffer = new WritableResourceStream($stream, $loop);

        $buffer->write("");
        $buffer->write(null);
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testWriteReturnsFalseWhenWritableResourceStreamIsFull(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $preventWrites = true;
        $loop = $this->createLoopMock();
        $loop
            ->expects($this->any())
            ->method('addWriteStream')
            ->will($this->returnCallback(function ($stream, $listener) use (&$preventWrites) {
                /** @var bool $preventWrites */
                if (!$preventWrites) {
                    call_user_func($listener, $stream);
                }
            }));

        $buffer = new WritableResourceStream($stream, $loop, 4);
        $buffer->on('error', $this->expectCallableNever());

        $this->assertTrue($buffer->write("foo"));
        $preventWrites = false;
        $this->assertFalse($buffer->write("bar\n"));
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     */
    public function testWriteReturnsFalseWhenWritableResourceStreamIsExactlyFull(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop, 3);

        $this->assertFalse($buffer->write("foo"));
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testWriteDetectsWhenOtherSideIsClosed(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        assert(is_array($pair));
        [$a, $b] = $pair;

        $loop = $this->createWriteableLoopMock();

        $buffer = new WritableResourceStream($a, $loop, 4);
        $buffer->on('error', $this->expectCallableOnce());

        fclose($b);

        $buffer->write("foo");
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testEmitsDrainAfterWriteWhichExceedsBuffer(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop, 2);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('drain', $this->expectCallableOnce());

        $buffer->write("foo");
        $buffer->handleWrite();
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testWriteInDrain(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop, 2);
        $buffer->on('error', $this->expectCallableNever());

        $buffer->once('drain', function () use ($buffer) {
            $buffer->write("bar\n");
            $buffer->handleWrite();
        });

        $this->assertFalse($buffer->write("foo\n"));
        $buffer->handleWrite();

        fseek($stream, 0);
        $this->assertSame("foo\nbar\n", stream_get_contents($stream));
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testDrainAfterWrite(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop, 2);

        $buffer->on('drain', $this->expectCallableOnce());

        $buffer->write("foo");
        $buffer->handleWrite();
    }

    /**
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testDrainAfterWriteWillRemoveResourceFromLoopWithoutClosing(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('removeWriteStream')->with($stream);

        $buffer = new WritableResourceStream($stream, $loop, 2);

        $buffer->on('drain', $this->expectCallableOnce());

        $buffer->on('close', $this->expectCallableNever());

        $buffer->write("foo");
        $buffer->handleWrite();
    }

    /**
     * @covers React\Stream\WritableResourceStream::handleWrite
     */
    public function testClosingDuringDrainAfterWriteWillRemoveResourceFromLoopOnceAndClose(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $loop->expects($this->once())->method('removeWriteStream')->with($stream);

        $buffer = new WritableResourceStream($stream, $loop, 2);

        $buffer->on('drain', function () use ($buffer) {
            $buffer->close();
        });

        $buffer->on('close', $this->expectCallableOnce());

        $buffer->write("foo");
        $buffer->handleWrite();
    }

    /**
     * @covers React\Stream\WritableResourceStream::end
     */
    public function testEndWithoutDataClosesImmediatelyIfWritableResourceStreamIsEmpty(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        $this->assertTrue($buffer->isWritable());
        $buffer->end();
        $this->assertFalse($buffer->isWritable());
    }

    /**
     * @covers React\Stream\WritableResourceStream::end
     */
    public function testEndWithoutDataDoesNotCloseIfWritableResourceStreamIsFull(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableNever());

        $buffer->write('foo');

        $this->assertTrue($buffer->isWritable());
        $buffer->end();
        $this->assertFalse($buffer->isWritable());
    }

    /**
     * @covers React\Stream\WritableResourceStream::end
     */
    public function testEndWithDataClosesImmediatelyIfWritableResourceStreamFlushes(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $filterBuffer = '';
        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        filter_append($stream, function ($chunk) use (&$filterBuffer) {
            $filterBuffer .= $chunk;
            return $chunk;
        });

        $this->assertTrue($buffer->isWritable());
        $buffer->end('final words');
        $this->assertFalse($buffer->isWritable());

        $buffer->handleWrite();
        $this->assertSame('final words', $filterBuffer);
    }

    /**
     * @covers React\Stream\WritableResourceStream::end
     */
    public function testEndWithDataDoesNotCloseImmediatelyIfWritableResourceStreamIsFull(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableNever());

        $buffer->write('foo');

        $this->assertTrue($buffer->isWritable());
        $buffer->end('final words');
        $this->assertFalse($buffer->isWritable());

        rewind($stream);
        $this->assertSame('', stream_get_contents($stream));
    }

    /**
     * @covers React\Stream\WritableResourceStream::isWritable
     * @covers React\Stream\WritableResourceStream::close
     */
    public function testClose(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        $this->assertTrue($buffer->isWritable());
        $buffer->close();
        $this->assertFalse($buffer->isWritable());

        $this->assertEquals([], $buffer->listeners('close'));
    }

    /**
     * @covers React\Stream\WritableResourceStream::close
     */
    public function testClosingAfterWriteRemovesStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $buffer = new WritableResourceStream($stream, $loop);

        $loop->expects($this->once())->method('removeWriteStream')->with($stream);

        $buffer->write('foo');
        $buffer->close();
    }

    /**
     * @covers React\Stream\WritableResourceStream::close
     */
    public function testClosingWithoutWritingDoesNotRemoveStreamFromLoop(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();
        $buffer = new WritableResourceStream($stream, $loop);

        $loop->expects($this->never())->method('removeWriteStream');

        $buffer->close();
    }

    /**
     * @covers React\Stream\WritableResourceStream::close
     */
    public function testDoubleCloseWillEmitOnlyOnce(): void
    {
        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);
        $buffer->on('close', $this->expectCallableOnce());

        $buffer->close();
        $buffer->close();
    }

    /**
     * @covers React\Stream\WritableResourceStream::write
     * @covers React\Stream\WritableResourceStream::close
     */
    public function testWritingToClosedWritableResourceStreamShouldNotWriteToStream(): void
    {
        if (PHP_VERSION_ID >= 80500) {
            $this->markTestSkipped('Since PHP 8.5 attempting to write to a closed stream will result in an error');
        }

        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $filterBuffer = '';
        $loop = $this->createLoopMock();

        $buffer = new WritableResourceStream($stream, $loop);

        filter_append($stream, function ($chunk) use (&$filterBuffer) {
            $filterBuffer .= $chunk;
            return $chunk;
        });

        $buffer->close();

        $buffer->write('foo');

        $buffer->handleWrite();
        $this->assertSame('', $filterBuffer);
    }

    public function testWritingToClosedStream(): void
    {
        if ('Darwin' === PHP_OS) {
            $this->markTestSkipped('OS X issue with shutting down pair for writing');
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        assert(is_array($pair));
        [$a, $b] = $pair;

        $loop = $this->createLoopMock();

        $error = null;

        $buffer = new WritableResourceStream($a, $loop);
        $buffer->on('error', function($message) use (&$error) {
            $error = $message;
        });

        $buffer->write('foo');
        $buffer->handleWrite();
        stream_socket_shutdown($b, STREAM_SHUT_RD);
        stream_socket_shutdown($a, STREAM_SHUT_RD);
        $buffer->write('bar');
        $buffer->handleWrite();

        $this->assertInstanceOf(\Exception::class, $error);
        $this->assertEqualsIgnoringCase('Unable to write to stream: fwrite(): send of 3 bytes failed with errno=32 Broken pipe', $error->getMessage());
    }

    private function createWriteableLoopMock(): LoopInterface
    {
        $loop = $this->createLoopMock();
        $loop
            ->expects($this->any())
            ->method('addWriteStream')
            ->will($this->returnCallback(function ($stream, $listener) {
                call_user_func($listener, $stream);
            }));

        return $loop;
    }

    /** @return MockObject&LoopInterface */
    private function createLoopMock(): MockObject
    {
        /** @var MockObject&LoopInterface */
        return $this->createMock(LoopInterface::class);
    }
}
