<?php

namespace React\Tests\Stream;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function expectCallableOnce(): callable
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    /** @param mixed $value */
    protected function expectCallableOnceWith($value): callable
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $callback;
    }

    protected function expectCallableNever(): callable
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    /** @return MockObject&callable */
    protected function createCallableMock(): MockObject
    {
        $builder = $this->getMockBuilder(\stdClass::class);
        if (method_exists($builder, 'addMethods')) {
            // PHPUnit 9+
            $mock = $builder->addMethods(['__invoke'])->getMock();
        } else {
            // legacy PHPUnit
            $mock = $builder->setMethods(['__invoke'])->getMock();
        }
        assert($mock instanceof MockObject && is_callable($mock));

        return $mock;
    }
}
