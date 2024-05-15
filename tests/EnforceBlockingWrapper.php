<?php

namespace React\Tests\Stream;

/**
 * Used to test dummy stream resources that do not support setting non-blocking mode
 *
 * @link https://www.php.net/manual/en/class.streamwrapper.php
 */
class EnforceBlockingWrapper
{
    /** @var resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return true;
    }

    public function stream_cast(int $cast_as): bool
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        if ($option === STREAM_OPTION_BLOCKING) {
            return false;
        }

        return true;
    }
}
