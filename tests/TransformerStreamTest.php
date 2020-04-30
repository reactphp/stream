<?php

namespace React\Tests\Stream;

use React\Stream\TransformerStream;
use React\Stream\WritableResourceStream;
use React\Tests\Stream\Stub\TransformerStreamStub;

/**
 * @covers React\Stream\TransformerStream
 */
class TransformerStreamTest extends TestCase
{
    private $output;
    private $transformer;

    public function setUp()
    {
        $stream = fopen('php://temp', 'r+');
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->output = new WritableResourceStream($stream, $loop);
        $this->transformer = new TransformerStreamStub($this->output);
    }

    /**
     * @test
     */
    public function itShouldPropagateEnd()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('end');
        $this->transformer = new TransformerStreamStub($this->output);
        $this->transformer->end();
    }

    /**
     * @test
     */
    public function itShouldPropagateEndData()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->expects($this->once())->method('write')->with('>data<');
        $this->transformer = new TransformerStreamStub($this->output);
        $this->transformer->end('data');
    }

    /**
     * @test
     */
    public function itShouldBeWritableByDefault()
    {
        $this->assertTrue($this->output->isWritable());
        $this->assertTrue($this->transformer->isWritable());
        $this->assertTrue(TransformerStream::withCallback($this->output, function(){})->isWritable());
    }

    /**
     * @test
     */
    public function itShouldntAcceptNonWritableOutput()
    {
        $this->output->close();
        $this->transformer = new TransformerStreamStub($this->output);
        $this->assertFalse($this->transformer->isWritable());
    }

    /**
     * @test
     */
    public function itShouldPropagateClose()
    {
        $this->transformer->close();
        $this->assertFalse($this->output->isWritable());
    }

    /**
     * @test
     */
    public function itShouldReversePropagateClose()
    {
        $this->output->close();
        $this->assertFalse($this->transformer->isWritable());
    }

    /**
     * @test
     */
    public function itShouldReversePropagateError()
    {
        $this->transformer->on('error', $this->expectCallableOnce());
        $this->transformer->on('close', $this->expectCallableOnce());
        $this->output->emit('error', array(new \Exception()));
        $this->assertFalse($this->transformer->isWritable());
        $this->assertEmpty($this->transformer->listeners('data'));
    }

    /**
     * @test
     */
    public function itShouldReversePropagateDrain()
    {
        $this->transformer->on('drain', $this->expectCallableOnce());
        $this->output->emit('drain');
    }


    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function itShouldRejectInvalidCallback()
    {
        TransformerStream::withCallback($this->output, 123);
    }

    /**
     * @test
     */
    public function itShouldExecuteCallable()
    {
        $this->transformer = TransformerStream::withCallback($this->output, $this->expectCallableOnce());
        $this->transformer->write('text');
    }

    /**
     * @test
     */
    public function itShouldBeClosedWhenBuildingWithCallableAndNonWritableOutput()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->method('isWritable')->willReturn(false);
        $this->output->expects($this->never())->method('write');
        $output = $this->output;
        $this->transformer = TransformerStream::withCallback($this->output, function($data) use ($output) {
            $output->write('~' . $data . '~');
        });
        $this->transformer->write('data');
    }

    /**
     * @test
     */
    public function itShouldSendDataToCallable()
    {
        $this->output = $this->getMockBuilder('React\Stream\WritableStreamInterface')->getMock();
        $this->output->method('isWritable')->willReturn(true);
        $this->output->expects($this->once())->method('write')->with('~data~');
        $output = $this->output;
        $this->transformer = TransformerStream::withCallback($this->output, function($data) use ($output) {
            $output->write('~' . $data . '~');
        });
        $this->transformer->write('data');
    }
}
