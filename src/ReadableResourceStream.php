<?php


namespace React\Stream;


class ReadableResourceStream
    extends AbstractReadableResourceStreamBase
    implements ReadableStreamInterface
{

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

}