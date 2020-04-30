<?php

namespace React\Stream;

use Evenement\EventEmitter;
use InvalidArgumentException;

/**
 * This is a basic TransformerStream to be used for custom transformer
 * implementations.
 */
class TransformerStream extends EventEmitter implements WritableStreamInterface
{
    protected $output;
    protected $callback;
    protected $closed = false;

    /**
     * @param WritableStreamInterface $output
     */
    public function __construct(WritableStreamInterface $output)
    {
        $this->output = $output;

        if (!$output->isWritable()) {
            $this->close();
            return;
        }

        $this->output->on('drain', array($this, 'handleDrain'));
        $this->output->on('error', array($this, 'handleError'));
        $this->output->on('close', array($this, 'close'));
    }

    /**
     * @param WritableStreamInterface $output
     * @param callable $callback
     */
    public static function withCallback(
        WritableStreamInterface $output,
        $callback
    )
    {
        if (!\is_callable($callback)) {
            throw new InvalidArgumentException('Invalid transformation callback given');
        }

        $stream = new self($output);
        $stream->callback = $callback;

        return $stream;
    }

    public function write($data)
    {
        if ($this->closed) {
            return false;
        }

        $callback = $this->callback;

        return is_callable($this->callback)
            ? $callback($data)
            : $this->writeToOutput($data);
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->output->end();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->output->close();

        $this->emit('close');
        $this->removeAllListeners();
    }

    /** @internal */
    public function handleDrain()
    {
        $this->emit('drain');
    }

    /** @internal */
    public function handleError(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->close();
    }

    /** @internal */
    protected function writeToOutput($data)
    {
        $this->output->write($data);
    }
}
