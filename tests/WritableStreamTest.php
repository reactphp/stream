<?php

namespace React\Tests\Stream;

use React\Stream\Event;
use React\Stream\ReadableStream;
use React\Stream\WritableStream;

/**
 * @covers React\Stream\WritableStream
 */
class WritableStreamTest extends TestCase
{
    /** @test */
    public function pipingStuffIntoItShouldWorkButDoNothing()
    {
        $readable = new ReadableStream();
        $through = new WritableStream();

        $readable->pipe($through);
        $readable->emit(Event::DATA, array('foo'));
    }

    /** @test */
    public function endShouldCloseTheStream()
    {
        $through = new WritableStream();
        $through->on(Event::DATA, $this->expectCallableNever());
        $through->end();

        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function endShouldWriteDataBeforeClosing()
    {
        $through = $this->getMock('React\Stream\WritableStream', array('write'));
        $through
            ->expects($this->once())
            ->method('write')
            ->with('foo');
        $through->end('foo');

        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function itShouldBeWritableByDefault()
    {
        $through = new WritableStream();
        $this->assertTrue($through->isWritable());
    }

    /** @test */
    public function closeShouldClose()
    {
        $through = new WritableStream();
        $through->close();

        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function doubleCloseShouldWork()
    {
        $through = new WritableStream();
        $through->close();
        $through->close();

        $this->assertFalse($through->isWritable());
    }
}
