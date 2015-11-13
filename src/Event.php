<?php

namespace React\Stream;

class Event
{
    // event on all data is written into a stream
    const FULL_DRAIN = 'full-drain';
    // event on stream closing
    const CLOSE = 'close';
    // event on stream is error
    const ERROR = 'error';
    // event on data is writing into a stream
    const DRAIN = 'drain';
    //
    const PIPE = 'pipe';
    // event on the last data is written into a stream
    const END = 'end';
    // event on a stream receive data
    const DATA = 'data';
}