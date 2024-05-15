<?php

namespace React\Tests\Stream;

use React\Stream\ThroughStream;
use React\Stream\WritableStreamInterface;

/**
 * @covers React\Stream\ThroughStream
 */
class ThroughStreamTest extends TestCase
{
    /** @test */
    public function itShouldReturnTrueForAnyDataWrittenToIt(): void
    {
        $through = new ThroughStream();
        $ret = $through->write('foo');

        $this->assertTrue($ret);
    }

    /** @test */
    public function itShouldEmitAnyDataWrittenToIt(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableOnceWith('foo'));
        $through->write('foo');
    }

    /** @test */
    public function itShouldEmitAnyDataWrittenToItPassedThruFunction(): void
    {
        $through = new ThroughStream('strtoupper');
        $through->on('data', $this->expectCallableOnceWith('FOO'));
        $through->write('foo');
    }

    /** @test */
    public function itShouldEmitAnyDataWrittenToItPassedThruCallback(): void
    {
        $through = new ThroughStream('strtoupper');
        $through->on('data', $this->expectCallableOnceWith('FOO'));
        $through->write('foo');
    }

    /** @test */
    public function itShouldEmitErrorAndCloseIfCallbackThrowsException(): void
    {
        $through = new ThroughStream(function () {
            throw new \RuntimeException();
        });
        $through->on('error', $this->expectCallableOnce());
        $through->on('close', $this->expectCallableOnce());
        $through->on('data', $this->expectCallableNever());
        $through->on('end', $this->expectCallableNever());

        $through->write('foo');

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function itShouldEmitErrorAndCloseIfCallbackThrowsExceptionOnEnd(): void
    {
        $through = new ThroughStream(function () {
            throw new \RuntimeException();
        });
        $through->on('error', $this->expectCallableOnce());
        $through->on('close', $this->expectCallableOnce());
        $through->on('data', $this->expectCallableNever());
        $through->on('end', $this->expectCallableNever());

        $through->end('foo');

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function itShouldReturnFalseForAnyDataWrittenToItWhenPaused(): void
    {
        $through = new ThroughStream();
        $through->pause();
        $ret = $through->write('foo');

        $this->assertFalse($ret);
    }

    /** @test */
    public function itShouldReturnFalseForAnyDataWrittenToItWhenDataEventEndsStream(): void
    {
        $through = new ThroughStream();
        $through->on('data', function () use ($through) {
            $through->end();
        });
        $ret = $through->write('foo');

        $this->assertFalse($ret);
    }

    /** @test */
    public function itShouldReturnFalseForAnyDataWrittenToItWhenDataEventClosesStream(): void
    {
        $through = new ThroughStream();
        $through->on('data', function () use ($through) {
            $through->close();
        });
        $ret = $through->write('foo');

        $this->assertFalse($ret);
    }

    /** @test */
    public function itShouldEmitDrainOnResumeAfterReturnFalseForAnyDataWrittenToItWhenPaused(): void
    {
        $through = new ThroughStream();
        $through->pause();
        $through->write('foo');

        $through->on('drain', $this->expectCallableOnce());
        $through->resume();
    }

    /** @test */
    public function itShouldNotEmitDrainOnResumeAfterClose(): void
    {
        $through = new ThroughStream();
        $through->close();

        $through->on('drain', $this->expectCallableNever());
        $through->resume();
    }

    /** @test */
    public function itShouldNotEmitDrainOnResumeAfterReturnFalseForAnyDataWrittenThatCausesStreamToClose(): void
    {
        $through = new ThroughStream();
        $through->on('data', function () use ($through) { $through->close(); });
        $through->write('foo');

        $through->on('drain', $this->expectCallableNever());
        $through->resume();
    }

    /** @test */
    public function itShouldReturnFalseForAnyDataWrittenToItAfterPausingFromDrainEvent(): void
    {
        $through = new ThroughStream();
        $through->pause();
        $through->write('foo');

        $through->on('drain', function () use ($through) { $through->pause(); });
        $through->resume();

        $this->assertFalse($through->write('bar'));
    }

    /** @test */
    public function itShouldReturnTrueForAnyDataWrittenToItWhenResumedAfterPause(): void
    {
        $through = new ThroughStream();
        $through->on('drain', $this->expectCallableNever());
        $through->pause();
        $through->resume();
        $ret = $through->write('foo');

        $this->assertTrue($ret);
    }

    /** @test */
    public function pipingStuffIntoItShouldWork(): void
    {
        $readable = new ThroughStream();

        $through = new ThroughStream();
        $through->on('data', $this->expectCallableOnceWith('foo'));

        $readable->pipe($through);
        $readable->emit('data', ['foo']);
    }

    /** @test */
    public function endShouldEmitEndAndClose(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableNever());
        $through->on('end', $this->expectCallableOnce());
        $through->on('close', $this->expectCallableOnce());
        $through->end();
    }

    /** @test */
    public function endShouldCloseTheStream(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableNever());
        $through->end();

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function endShouldWriteDataBeforeClosing(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableOnceWith('foo'));
        $through->end('foo');

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function endTwiceShouldOnlyEmitOnce(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableOnceWith('first'));
        $through->end('first');
        $through->end('ignored');
    }

    /** @test */
    public function writeAfterEndShouldReturnFalse(): void
    {
        $through = new ThroughStream();
        $through->on('data', $this->expectCallableNever());
        $through->end();

        $this->assertFalse($through->write('foo'));
    }

    /** @test */
    public function writeDataWillCloseStreamShouldReturnFalse(): void
    {
        $through = new ThroughStream();
        $through->on('data', [$through, 'close']);

        $this->assertFalse($through->write('foo'));
    }

    /** @test */
    public function writeDataToPausedShouldReturnFalse(): void
    {
        $through = new ThroughStream();
        $through->pause();

        $this->assertFalse($through->write('foo'));
    }

    /** @test */
    public function writeDataToResumedShouldReturnTrue(): void
    {
        $through = new ThroughStream();
        $through->pause();
        $through->resume();

        $this->assertTrue($through->write('foo'));
    }

    /** @test */
    public function itShouldBeReadableByDefault(): void
    {
        $through = new ThroughStream();
        $this->assertTrue($through->isReadable());
    }

    /** @test */
    public function itShouldBeWritableByDefault(): void
    {
        $through = new ThroughStream();
        $this->assertTrue($through->isWritable());
    }

    /** @test */
    public function closeShouldCloseOnce(): void
    {
        $through = new ThroughStream();

        $through->on('close', $this->expectCallableOnce());

        $through->close();

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function doubleCloseShouldCloseOnce(): void
    {
        $through = new ThroughStream();

        $through->on('close', $this->expectCallableOnce());

        $through->close();
        $through->close();

        $this->assertFalse($through->isReadable());
        $this->assertFalse($through->isWritable());
    }

    /** @test */
    public function pipeShouldPipeCorrectly(): void
    {
        $output = $this->createMock(WritableStreamInterface::class);
        $output->expects($this->any())->method('isWritable')->willReturn(True);
        $output
            ->expects($this->once())
            ->method('write')
            ->with('foo');
        assert($output instanceof WritableStreamInterface);

        $through = new ThroughStream();
        $through->pipe($output);
        $through->write('foo');
    }
}
