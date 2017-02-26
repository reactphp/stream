<?php

namespace React\Tests\Stream;

use React\Stream\Buffer;
use React\Stream\ReadableStream;
use React\Stream\Util;

/**
 * @covers React\Stream\Util
 */
class UtilTest extends TestCase
{
    public function testPipeReturnsDestinationStream()
    {
        $readable = $this->getMock('React\Stream\ReadableStreamInterface');

        $writable = $this->getMock('React\Stream\WritableStreamInterface');

        $ret = Util::pipe($readable, $writable);

        $this->assertSame($writable, $ret);
    }

    public function testPipeNonReadableSourceShouldDoNothing()
    {
        $readable = $this->getMock('React\Stream\ReadableStreamInterface');
        $readable
            ->expects($this->any())
            ->method('isReadable')
            ->willReturn(false);

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->never())
            ->method('isWritable');
        $writable
            ->expects($this->never())
            ->method('end');

        Util::pipe($readable, $writable);
    }

    public function testPipeIntoNonWritableDestinationShouldPauseSource()
    {
        $readable = $this->getMock('React\Stream\ReadableStreamInterface');
        $readable
            ->expects($this->any())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('pause');

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(false);
        $writable
            ->expects($this->never())
            ->method('end');

        Util::pipe($readable, $writable);
    }

    public function testPipeWithEnd()
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('end');

        Util::pipe($readable, $writable);

        $readable->end();
    }

    public function testPipeWithoutEnd()
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->never())
            ->method('end');

        Util::pipe($readable, $writable, array('end' => false));

        $readable->end();
    }

    public function testPipeWithTooSlowWritableShouldPauseReadable()
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('write')
            ->with('some data')
            ->will($this->returnValue(false));

        $readable->pipe($writable);

        $this->assertFalse($readable->paused);
        $readable->write('some data');
        $this->assertTrue($readable->paused);
    }

    public function testPipeWithTooSlowWritableShouldResumeOnDrain()
    {
        $readable = new Stub\ReadableStreamStub();

        $onDrain = null;

        $writable = $this->getMock('React\Stream\WritableStreamInterface');
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('on')
            ->with('drain', $this->isInstanceOf('Closure'))
            ->will($this->returnCallback(function ($name, $callback) use (&$onDrain) {
                $onDrain = $callback;
            }));

        $readable->pipe($writable);
        $readable->pause();

        $this->assertTrue($readable->paused);
        $onDrain();
        $this->assertFalse($readable->paused);
    }

    public function testPipeWithBuffer()
    {
        $readable = new Stub\ReadableStreamStub();

        $stream = fopen('php://temp', 'r+');
        $loop = $this->createLoopMock();
        $buffer = new Buffer($stream, $loop);

        $readable->pipe($buffer);

        $readable->write('hello, I am some ');
        $readable->write('random data');

        $buffer->handleWrite();
        rewind($stream);
        $this->assertSame('hello, I am some random data', stream_get_contents($stream));
    }

    /** @test */
    public function forwardEventsShouldSetupForwards()
    {
        $source = new ReadableStream();
        $target = new ReadableStream();

        Util::forwardEvents($source, $target, array('data'));
        $target->on('data', $this->expectCallableOnce());
        $target->on('foo', $this->expectCallableNever());

        $source->emit('data', array('hello'));
        $source->emit('foo', array('bar'));
    }

    private function createLoopMock()
    {
        return $this->getMock('React\EventLoop\LoopInterface');
    }

    private function notEqualTo($value)
    {
        return new \PHPUnit_Framework_Constraint_Not($value);
    }
}
