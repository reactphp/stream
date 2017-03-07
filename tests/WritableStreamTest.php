<?php

namespace React\Tests\Stream;

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
        $readable->emit('data', array('foo'));
    }

    /** @test */
    public function endShouldCloseTheStream()
    {
        $through = new WritableStream();
        $through->on('data', $this->expectCallableNever());
        $through->end();

        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function endShouldWriteDataBeforeClosing()
    {
        $through = $this->getMockBuilder('React\Stream\WritableStream')->setMethods(array('write'))->getMock();
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
    public function closeShouldEmitCloseEvent()
    {
        $through = new WritableStream();
        $through->on('close', $this->expectCallableOnce());
        $through->on('end', $this->expectCallableNever());

        $through->close();
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
