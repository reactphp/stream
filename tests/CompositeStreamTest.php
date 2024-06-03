<?php

namespace React\Tests\Stream;

use React\Stream\CompositeStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;

/**
 * @covers React\Stream\CompositeStream
 */
class CompositeStreamTest extends TestCase
{
    /** @test */
    public function itShouldCloseReadableIfNotWritable(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('close');
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->once())
            ->method('isWritable')
            ->willReturn(false);
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);

        $composite->on('close', $this->expectCallableNever());
        $composite->close();
    }

    /** @test */
    public function itShouldCloseWritableIfNotReadable(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(false);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->once())
            ->method('close');
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);

        $composite->on('close', $this->expectCallableNever());
        $composite->close();
    }

    /** @test */
    public function itShouldForwardWritableCallsToWritableStream(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->once())
            ->method('write')
            ->with('foo');
        $writable
            ->expects($this->exactly(2))
            ->method('isWritable')
            ->willReturn(true);
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);
        $composite->write('foo');
        $composite->isWritable();
    }

    /** @test */
    public function itShouldForwardReadableCallsToReadableStream(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->exactly(2))
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('pause');
        $readable
            ->expects($this->once())
            ->method('resume');
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->any())
            ->method('isWritable')
            ->willReturn(true);
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);
        $composite->isReadable();
        $composite->pause();
        $composite->resume();
    }

    /** @test */
    public function itShouldNotForwardResumeIfStreamIsNotWritable(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->never())
            ->method('resume');
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->exactly(2))
            ->method('isWritable')
            ->willReturnOnConsecutiveCalls(true, false);
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);
        $composite->resume();
    }

    /** @test */
    public function endShouldDelegateToWritableWithData(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->once())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('end')
            ->with('foo');
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);
        $composite->end('foo');
    }

    /** @test */
    public function closeShouldCloseBothStreams(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        $readable
            ->expects($this->once())
            ->method('close');
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable
            ->expects($this->once())
            ->method('isWritable')
            ->willReturn(true);
        $writable
            ->expects($this->once())
            ->method('close');
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);
        $composite->close();
    }

    /** @test */
    public function itShouldForwardCloseOnlyOnce(): void
    {
        $readable = new ThroughStream();
        $writable = new ThroughStream();

        $composite = new CompositeStream($readable, $writable);
        $composite->on('close', $this->expectCallableOnce());

        $readable->close();
        $writable->close();
    }

    /** @test */
    public function itShouldForwardCloseAndRemoveAllListeners(): void
    {
        $in = new ThroughStream();

        $composite = new CompositeStream($in, $in);
        $composite->on('close', $this->expectCallableOnce());

        $this->assertTrue($composite->isReadable());
        $this->assertTrue($composite->isWritable());
        $this->assertCount(1, $composite->listeners('close'));

        $composite->close();

        $this->assertFalse($composite->isReadable());
        $this->assertFalse($composite->isWritable());
        $this->assertCount(0, $composite->listeners('close'));
    }

    /** @test */
    public function itShouldReceiveForwardedEvents(): void
    {
        $readable = new ThroughStream();
        $writable = new ThroughStream();

        $composite = new CompositeStream($readable, $writable);
        $composite->on('data', $this->expectCallableOnce());
        $composite->on('drain', $this->expectCallableOnce());

        $readable->emit('data', ['foo']);
        $writable->emit('drain');
    }

    /** @test */
    public function itShouldHandlePipingCorrectly(): void
    {
        $readable = $this->createMock(ReadableStreamInterface::class);
        $readable
            ->expects($this->once())
            ->method('isReadable')
            ->willReturn(true);
        assert($readable instanceof ReadableStreamInterface);

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable->expects($this->any())->method('isWritable')->willReturn(True);
        $writable
            ->expects($this->once())
            ->method('write')
            ->with('foo');
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);

        $input = new ThroughStream();
        $input->pipe($composite);
        $input->emit('data', ['foo']);
    }

    /** @test */
    public function itShouldForwardPipeCallsToReadableStream(): void
    {
        $readable = new ThroughStream();

        $writable = $this->createMock(WritableStreamInterface::class);
        $writable->expects($this->any())->method('isWritable')->willReturn(True);
        assert($writable instanceof WritableStreamInterface);

        $composite = new CompositeStream($readable, $writable);

        $output = $this->createMock(WritableStreamInterface::class);
        $output->expects($this->any())->method('isWritable')->willReturn(True);
        $output
            ->expects($this->once())
            ->method('write')
            ->with('foo');
        assert($output instanceof WritableStreamInterface);

        $composite->pipe($output);
        $readable->emit('data', ['foo']);
    }
}
