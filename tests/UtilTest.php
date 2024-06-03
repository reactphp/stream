<?php

namespace React\Tests\Stream;

use React\EventLoop\LoopInterface;
use React\Stream\CompositeStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use React\Stream\Util;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;

/**
 * @covers React\Stream\Util
 */
class UtilTest extends TestCase
{
    public function testPipeReturnsDestinationStream(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        assert($writable instanceof WritableStreamInterface);

        $ret = Util::pipe($readable, $writable);

        $this->assertSame($writable, $ret);
    }

    public function testPipeNonReadableSourceShouldDoNothing(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->any())
            ->method('isReadable')
            ->willReturn(false);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->never())
            ->method('isWritable');
        $writable
            ->expects($this->never())
            ->method('end');
        assert($writable instanceof WritableStreamInterface);

        Util::pipe($readable, $writable);
    }

    public function testPipeIntoNonWritableDestinationShouldPauseSource(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->any())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('pause');
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(false);
        $writable
            ->expects($this->never())
            ->method('end');
        assert($writable instanceof WritableStreamInterface);

        Util::pipe($readable, $writable);
    }

    public function testPipeClosingDestPausesSource(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->any())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('pause');
        assert($readable instanceof ReadableStreamInterface);

        $writable = new ThroughStream();

        Util::pipe($readable, $writable);

        $writable->close();
    }

    public function testPipeWithEnd(): void
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('end');
        assert($writable instanceof WritableStreamInterface);

        Util::pipe($readable, $writable);

        $readable->end();
    }

    public function testPipeWithoutEnd(): void
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->never())
            ->method('end');
        assert($writable instanceof WritableStreamInterface);

        Util::pipe($readable, $writable, ['end' => false]);

        $readable->end();
    }

    public function testPipeWithTooSlowWritableShouldPauseReadable(): void
    {
        $readable = new Stub\ReadableStreamStub();

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('write')
            ->with('some data')
            ->will($this->returnValue(false));
        assert($writable instanceof WritableStreamInterface);

        $readable->pipe($writable);

        $this->assertFalse($readable->paused);
        $readable->write('some data');
        $this->assertTrue($readable->paused);
    }

    public function testPipeWithTooSlowWritableShouldResumeOnDrain(): void
    {
        $readable = new Stub\ReadableStreamStub();

        $onDrain = null;

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->any())
            ->method('on')
            ->will($this->returnCallback(function ($name, $callback) use (&$onDrain) {
                if ($name === 'drain') {
                    $onDrain = $callback;
                }
            }));
        assert($writable instanceof WritableStreamInterface);

        $readable->pipe($writable);
        $readable->pause();

        $this->assertTrue($readable->paused);
        $this->assertNotNull($onDrain);
        $onDrain();
        $this->assertFalse($readable->paused);
    }

    public function testPipeWithWritableResourceStream(): void
    {
        $readable = new Stub\ReadableStreamStub();

        $stream = fopen('php://temp', 'r+');
        assert(is_resource($stream));

        $loop = $this->createMock(LoopInterface::class);
        assert($loop instanceof LoopInterface);
        $buffer = new WritableResourceStream($stream, $loop);

        $readable->pipe($buffer);

        $readable->write('hello, I am some ');
        $readable->write('random data');

        $buffer->handleWrite();
        rewind($stream);
        $this->assertSame('hello, I am some random data', stream_get_contents($stream));
    }

    public function testPipeSetsUpListeners(): void
    {
        $source = new ThroughStream();
        $dest = new ThroughStream();

        $this->assertCount(0, $source->listeners('data'));
        $this->assertCount(0, $source->listeners('end'));
        $this->assertCount(0, $dest->listeners('drain'));

        Util::pipe($source, $dest);

        $this->assertCount(1, $source->listeners('data'));
        $this->assertCount(1, $source->listeners('end'));
        $this->assertCount(1, $dest->listeners('drain'));
    }

    public function testPipeClosingSourceRemovesListeners(): void
    {
        $source = new ThroughStream();
        $dest = new ThroughStream();

        Util::pipe($source, $dest);

        $source->close();

        $this->assertCount(0, $source->listeners('data'));
        $this->assertCount(0, $source->listeners('end'));
        $this->assertCount(0, $dest->listeners('drain'));
    }

    public function testPipeClosingDestRemovesListeners(): void
    {
        $source = new ThroughStream();
        $dest = new ThroughStream();

        Util::pipe($source, $dest);

        $dest->close();

        $this->assertCount(0, $source->listeners('data'));
        $this->assertCount(0, $source->listeners('end'));
        $this->assertCount(0, $dest->listeners('drain'));
    }

    public function testPipeDuplexIntoSelfEndsOnEnd(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable->expects($this->any())->method('isReadable')->willReturn(true);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable->expects($this->any())->method('isWritable')->willReturn(true);
        assert($writable instanceof WritableStreamInterface);

        $duplex = new CompositeStream($readable, $writable);

        Util::pipe($duplex, $duplex);

        $writable->expects($this->once())->method('end');

        $duplex->emit('end');
    }

    /** @test */
    public function forwardEventsShouldSetupForwards(): void
    {
        $source = new ThroughStream();
        $target = new ThroughStream();

        Util::forwardEvents($source, $target, ['data']);
        $target->on('data', $this->expectCallableOnce());
        $target->on('foo', $this->expectCallableNever());

        $source->emit('data', ['hello']);
        $source->emit('foo', ['bar']);
    }
}
