<?php

namespace React\Tests\Stream;

use React\Stream\Event;

class EventsBackwardsCompatibilityTest extends TestCase
{
    public function eventNamesWontBreakCompatibilityProvider()
    {
        return array(
            array('close', Event\CLOSE),
            array('data', Event\DATA),
            array('drain', Event\DRAIN),
            array('end', Event\END),
            array('error', Event\ERROR),
            array('pipe', Event\PIPE),
        );
    }

    /**
     * @param string $expected
     * @param string $actual
     *
     * @dataProvider eventNamesWontBreakCompatibilityProvider
     */
    public function testEventNamesWontBreakCompatibility($expected, $actual)
    {
        $this->assertEquals($expected, $actual, 'Event names must be compatible with previous versions.');
    }
}
