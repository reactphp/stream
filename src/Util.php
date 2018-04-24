<?php

namespace React\Stream;

use React\Stream\Event;

final class Util
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

        $dest->emit(Event\PIPE, array($source));

        // forward all source data events as $dest->write()
        $source->on(Event\DATA, $dataer = function ($data) use ($source, $dest) {
            $feedMore = $dest->write($data);

            if (false === $feedMore) {
                $source->pause();
            }
        });
        $dest->on(Event\CLOSE, function () use ($source, $dataer) {
            $source->removeListener(Event\DATA, $dataer);
            $source->pause();
        });

        // forward destination drain as $source->resume()
        $dest->on(Event\DRAIN, $drainer = function () use ($source) {
            $source->resume();
        });
        $source->on(Event\CLOSE, function () use ($dest, $drainer) {
            $dest->removeListener(Event\DRAIN, $drainer);
        });

        // forward end event from source as $dest->end()
        $end = isset($options['end']) ? $options['end'] : true;
        if ($end) {
            $source->on(Event\END, $ender = function () use ($dest) {
                $dest->end();
            });
            $dest->on(Event\CLOSE, function () use ($source, $ender) {
                $source->removeListener(Event\END, $ender);
            });
        }

        return $dest;
    }

    public static function forwardEvents($source, $target, array $events)
    {
        foreach ($events as $event) {
            $source->on($event, function () use ($event, $target) {
                $target->emit($event, \func_get_args());
            });
        }
    }
}
