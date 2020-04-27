<?php


namespace React\Stream;

use React\EventLoop\LoopInterface;

class StreamServerStream
    extends AbstractReadableResourceStreamBase
    implements StreamServerInterface
{

    public function __construct($stream, LoopInterface $loop, $readChunkSize = null)
    {

        if (is_string($stream))
        {

            $path = $stream;

            if (file_exists($path))
            {
                throw new \RuntimeException(sprintf('Path %s already exists. Is another instance running?', $path));
            }

            $dir = realpath(dirname($path));

            if (!file_exists($dir))
            {
                throw new \RuntimeException(sprintf('Parent directory of %s does not exist', $path));
            }

            if (!is_dir($dir))
            {
                throw new \RuntimeException(sprintf('Parent of %s is not a directory', $path));
            }

            if (!is_writable($dir))
            {
                throw new \RuntimeException(sprintf('Parent directory of %s is not writable', $path));
            }

            $stream = stream_socket_server("unix://$path", $errNo, $errString);

            if (!$stream)
            {
                throw new \RuntimeException(
                    sprintf('Could not create socket %s (Error %d): %s', $path, $errNo, $errString)
                );
            }

            register_shutdown_function(
                static function () use
                (
                    $path,
                    $stream
                )
                {
                    // cleanup
                    echo "closing $path";
                    fclose($stream);
                    unlink($path);
                }
            );
        }

        parent::__construct($stream, $loop, $readChunkSize);
    }

    /** @internal */
    public function handleData()
    {
        # new connections! probably...

        // stream_set_blocking() has no effect on stream_socket_accept,
        // and stream_socket_accept will still block when the socket is non-blocking,
        // unless timeout is 0, but if timeout is 0 and there is no waiting connections,
        // php will throw PHP Warning: stream_socket_accept(): accept failed: Connection timed
        // so it seems using @ to make php stfu is the easiest way here
        while ($newStream = @stream_socket_accept($this->stream, 0, $peerName))
        {
            stream_set_blocking($newStream, true);
            $this->emit('new', array($newStream, $peerName, $this));
        }
    }
}
