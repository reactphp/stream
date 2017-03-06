<?php

namespace React\Tests\Stream;

use React\Stream\ReadableStream;

class ReadableStreamTest extends TestCase
{
    /** @test */
    public function itShouldBeReadableByDefault()
    {
        $readable = new ReadableStream();
        $this->assertTrue($readable->isReadable());
    }

    /** @test */
    public function pauseShouldDoNothing()
    {
        $readable = new ReadableStream();
        $readable->pause();
    }

    /** @test */
    public function resumeShouldDoNothing()
    {
        $readable = new ReadableStream();
        $readable->resume();
    }

    /** @test */
    public function pipeShouldReturnDestination()
    {
        $dest = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $readable = new ReadableStream();

        $this->assertSame($dest, $readable->pipe($dest));
    }

    /** @test */
    public function closeShouldClose()
    {
        $readable = new ReadableStream();
        $readable->close();

        $this->assertFalse($readable->isReadable());
    }

    /** @test */
    public function closeShouldEmitCloseEvent()
    {
        $readable = new ReadableStream();
        $readable->on('close', $this->expectCallableOnce());
        $readable->on('end', $this->expectCallableNever());

        $readable->close();
    }

    /** @test */
    public function doubleCloseShouldWork()
    {
        $readable = new ReadableStream();
        $readable->close();
        $readable->close();

        $this->assertFalse($readable->isReadable());
    }
}
