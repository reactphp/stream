<?php

namespace React\Tests\Stream;

use React\Stream\Stream;

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

        $conn->handleData();
        $this->assertSame("foobar\n", $capturedData);
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
     */
    public function testEnd()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();

        $conn = new Stream($stream, $loop);
        $conn->end();

        $this->assertFalse(is_resource($stream));
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
        $conn->on('data', function ($data, $stream) {
            $stream->close();
        });

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
        return $this->getMock('React\EventLoop\LoopInterface');
    }
}
