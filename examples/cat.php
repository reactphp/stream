<?php

use React\EventLoop\Factory;
use React\Stream\Stream;
use React\Stream\ReadableResourceStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$stdout = new Stream(STDOUT, $loop);
$stdout->pause();

$stdin = new ReadableResourceStream(STDIN, $loop);
$stdin->pipe($stdout);

$loop->run();
