<?php


namespace React\Tests\Stream\Stub;


use React\Stream\TransformerStream;

class TransformerStreamStub extends TransformerStream
{
    public function write($data)
    {
        $this->output->write('>'. $data . '<');
    }
}