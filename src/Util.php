<?php

namespace React\Stream;

class Util
{
    /**
     * Pipes all the data from the given $source into the $dest
     *
     * @param ReadableStreamInterface $source
     * @param WritableStreamInterface $dest
     * @param array $options
     * @return WritableStreamInterface $dest stream as-is
     * @see ReadableStreamInterface::pipe() for more details
     */
    public static function pipe(ReadableStreamInterface $source, WritableStreamInterface $dest, array $options = array())
    {
        // source not readable => NO-OP
        if (!$source->isReadable()) {
            return $dest;
        }

        // destination not writable => just pause() source
        if (!$dest->isWritable()) {
            $source->pause();

            return $dest;
        }

        $dest->emit('pipe', array($source));

        $source->on('data', function ($data) use ($source, $dest) {
            $feedMore = $dest->write($data);

            if (false === $feedMore) {
                $source->pause();
            }
        });

        $dest->on('drain', function () use ($source) {
            $source->resume();
        });

        $end = isset($options['end']) ? $options['end'] : true;
        if ($end && $source !== $dest) {
            $source->on('end', function () use ($dest) {
                $dest->end();
            });
        }

        return $dest;
    }

    public static function forwardEvents($source, $target, array $events)
    {
        foreach ($events as $event) {
            $source->on($event, function () use ($event, $target) {
                $target->emit($event, func_get_args());
            });
        }
    }
}
