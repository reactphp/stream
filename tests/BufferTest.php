<?php

namespace React\Tests\Stream;

use React\Stream\Buffer;

class BufferTest extends TestCase
{
    /**
     * @covers React\Stream\Buffer::__construct
     */
    public function testConstructor()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
    }

    /**
     * @covers React\Stream\Buffer::write
     * @covers React\Stream\Buffer::handleWrite
     */
    public function testWrite()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());

        $buffer->write("foobar\n");
        rewind($stream);
        $this->assertSame("foobar\n", fread($stream, 1024));
    }

    /**
     * @covers React\Stream\Buffer::write
     * @covers React\Stream\Buffer::handleWrite
     */
    public function testWritePromiseIsResolved()
    {
        // create a stream that writes at max 4 bytes but without signalling EOF
        // after 4 bytes fwrite will always return 0
        stream_wrapper_register("limited-stream", '\React\Tests\Stream\Stub\LimitedStreamStub');
        $stream = fopen('limited-stream://4', 'r+');

        // Don't write to stream at first
        $loop = $this->createWriteableLoopMock();
        $loop->preventWrites = true;
        
        // Setup buffer and write <=4 bytes
        // The promise is expected to be resolved as soon the loop writes data
        $buffer = new Buffer($stream, $loop);
        $firstWrite = $buffer->write("foo");
        $firstWrite->then($this->expectCallableOnce());


        // close buffer after first writes, so other writes can not resolve
        $firstWrite->then(function() use ($buffer) {
            $buffer->close();
        });

        // Open & trigger loop/buffer for writing
        $loop->preventWrites = false;
        $buffer->listening = false;

        // Since we already wrote 3 bytes("foo") and then closed the stream
        // "bar" should not be resolved as written
        $buffer->write("bar")->then($this->expectCallableNever());
    }

    /**
     * @covers React\Stream\Buffer::write
     * @covers React\Stream\Buffer::handleWrite
     */
    public function testWriteDetectsWhenOtherSideIsClosed()
    {
        list($a, $b) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $loop = $this->createWriteableLoopMock();

        $buffer = new Buffer($a, $loop);
        $buffer->softLimit = 4;
        $buffer->on('error', $this->expectCallableOnce());

        fclose($b);

        $buffer->write("foo");
    }

    /**
     * @covers React\Stream\Buffer::write
     * @covers React\Stream\Buffer::handleWrite
     */
    public function testBufferFullAndDrain()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();
        $loop->preventWrites = true;

        $buffer = new Buffer($stream, $loop);
        $buffer->softLimit = 3;
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('full', $this->expectCallableOnce());
        $buffer->on('drain', $this->expectCallableOnce());

        $buffer->write("foo");
        $loop->preventWrites = false;
        $buffer->listening = false;
        $buffer->write("bar\n");
    }

    /**
     * @covers React\Stream\Buffer::end
     */
    public function testEnd()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        $this->assertTrue($buffer->isWritable());
        $buffer->end();
        $this->assertFalse($buffer->isWritable());
    }

    /**
     * @covers React\Stream\Buffer::end
     */
    public function testEndWithData()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        $buffer->end('final words');

        rewind($stream);
        $this->assertSame('final words', stream_get_contents($stream));
    }

    /**
     * @covers React\Stream\Buffer::isWritable
     * @covers React\Stream\Buffer::close
     */
    public function testClose()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', $this->expectCallableNever());
        $buffer->on('close', $this->expectCallableOnce());

        $this->assertTrue($buffer->isWritable());
        $buffer->close();
        $this->assertFalse($buffer->isWritable());
    }

    /**
     * @covers React\Stream\Buffer::write
     * @covers React\Stream\Buffer::close
     */
    public function testWritingToClosedBufferShouldNotWriteToStream()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createWriteableLoopMock();

        $buffer = new Buffer($stream, $loop);
        $buffer->close();

        $buffer->write('foo');

        rewind($stream);
        $this->assertSame('', stream_get_contents($stream));
    }

    /**
     * @covers React\Stream\Buffer::handleWrite
     * @covers React\Stream\Buffer::errorHandler
     */
    public function testError()
    {
        $stream = null;
        $loop = $this->createWriteableLoopMock();

        $error = null;

        $buffer = new Buffer($stream, $loop);
        $buffer->on('error', function ($message) use (&$error) {
            $error = $message;
        });

        $buffer->write('Attempting to write to bad stream');
        $this->assertInstanceOf('Exception', $error);
        $this->assertSame('Tried to write to invalid stream.', $error->getMessage());
    }

    public function testWritingToClosedStream()
    {
        if ('Darwin' === PHP_OS) {
            $this->markTestSkipped('OS X issue with shutting down pair for writing');
        }

        list($a, $b) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $loop = $this->createWriteableLoopMock();

        $error = null;

        $buffer = new Buffer($a, $loop);
        $buffer->on('error', function($message) use (&$error) {
            $error = $message;
        });

        $buffer->write('foo');
        stream_socket_shutdown($b, STREAM_SHUT_RD);
        stream_socket_shutdown($a, STREAM_SHUT_RD);
        $buffer->write('bar');

        $this->assertInstanceOf('Exception', $error);
        $this->assertSame('Tried to write to closed stream.', $error->getMessage());
    }

    private function createWriteableLoopMock()
    {
        $loop = $this->createLoopMock();
        $loop->preventWrites = false;
        $loop
            ->expects($this->any())
            ->method('addWriteStream')
            ->will($this->returnCallback(function ($stream, $listener) use ($loop) {
                if (!$loop->preventWrites) {
                    call_user_func($listener, $stream);
                }
            }));

        return $loop;
    }

    private function createLoopMock()
    {
        return $this->getMock('React\EventLoop\LoopInterface');
    }
}
