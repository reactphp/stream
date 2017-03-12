<?php

use React\EventLoop\Factory;
use React\Stream\Stream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$stdout = new Stream(STDOUT, $loop);
$stdout->pause();

$stdin = new Stream(STDIN, $loop);
$stdin->pipe($stdout);

$loop->run();
