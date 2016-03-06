<?php

require __DIR__ . '/../vendor/autoload.php';

$args = getopt('i:o:t:');
$if = isset($args['i']) ? $args['i'] : '/dev/zero';
$of = isset($args['o']) ? $args['o'] : '/dev/null';
$t  = isset($args['t']) ? $args['t'] : 1;

echo 'piping for ' . $t . ' second(s) from ' . $if . ' to ' . $of . '...'. PHP_EOL;

$loop = new React\EventLoop\StreamSelectLoop();

// setup input and output streams and pipe inbetween
$in = new React\Stream\Stream(fopen($if, 'r'), $loop);
$out = new React\Stream\Stream(fopen($of, 'w'), $loop);
$out->pause();
$in->pipe($out);

// stop input stream in $t seconds
$start = microtime(true);
$timeout = $loop->addTimer($t, function () use ($in, &$bytes) {
    $in->close();
});

// print stream position once stream closes
$in->on('close', function () use ($in, $start, $timeout) {
    $t = microtime(true) - $start;
    $timeout->cancel();

    $bytes = ftell($in->stream);

    echo 'read ' . $bytes . ' byte(s) in ' . round($t, 3) . ' second(s) => ' . round($bytes / 1024 / 1024 / $t, 1) . ' MiB/s' . PHP_EOL;
});

$loop->run();
