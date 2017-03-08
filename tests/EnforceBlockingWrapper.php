<?php

namespace React\Tests\Stream;

class EnforceBlockingWrapper
{
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        if ($option === STREAM_OPTION_BLOCKING) {
            return false;
        }

        return true;
    }
}
