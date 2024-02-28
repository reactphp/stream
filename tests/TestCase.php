<?php

namespace React\Tests\Stream;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $callback;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        $builder = $this->getMockBuilder(\stdClass::class);
        if (method_exists($builder, 'addMethods')) {
            // PHPUnit 9+
            return $builder->addMethods(['__invoke'])->getMock();
        } else {
            // legacy PHPUnit
            return $builder->setMethods(['__invoke'])->getMock();
        }
    }
}
