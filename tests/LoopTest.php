<?php
/*
 * Copyright 2015 Stephen Coakley <me@stephencoakley.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy
 * of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

namespace Evflow\Tests;

use Evflow\Loop;
use Evflow\LoopInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LoopTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LoopInterface A mock loop instance.
     */
    protected $loop;

    public function setUp()
    {
        $this->loop = $this->getMockBuilder(LoopInterface::class)
                            ->getMock();
    }

    public function testInstanceReturnsInstance()
    {
        Loop::init($this->loop);
        $this->assertSame($this->loop, Loop::instance());
    }

    /**
     * @expectedException Evflow\LoopInitializedException
     */
    public function testInitCanOnlyBeCalledOnce()
    {
        Loop::init($this->loop);
        Loop::init($this->loop);
    }

    public function testInitCalledAutomatically()
    {
        $this->assertInstanceOf(LoopInterface::class, Loop::instance());
    }

    public function testIsRunningIsCalled()
    {
        $this->loop->expects($this->once())
                   ->method('isRunning');

        Loop::init($this->loop);
        Loop::isRunning();
    }

    public function testTickIsCalled()
    {
        $this->loop->expects($this->once())
                   ->method('tick');

        Loop::init($this->loop);
        Loop::tick();
    }

    public function testRunIsCalled()
    {
        $this->loop->expects($this->once())
                   ->method('run');

        Loop::init($this->loop);
        Loop::run();
    }

    public function testStopIsCalled()
    {
        $this->loop->expects($this->once())
                   ->method('stop');

        Loop::init($this->loop);
        Loop::stop();
    }
}
