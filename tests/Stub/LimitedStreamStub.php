<?php

namespace React\Tests\Stream\Stub;

class LimitedStreamStub
{

    protected $canWrite;

    public function stream_open($path)
    {
        $url = parse_url($path);
        $this->canWrite = (int) $url['host'];
        return true;
    }

    public function stream_write($data)
    {
        $written = min($this->canWrite, strlen($data));
        $this->canWrite -= $written;

        return $written;
    }

    public function stream_eof()
    {
        return false;
    }
}

