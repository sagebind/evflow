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

namespace Evflow\Timers;

use Evflow\DefaultLoop;
use Evflow\LoopInterface;

class Timer
{
    protected $interval;
    protected $periodic;
    protected $callback;

    public function __construct($interval, callable $callback, $periodic = false, LoopInterface $loop = null)
    {
        $this->interval = $interval;
        $this->periodic = !!$periodic;
        $this->callback = $callback;
        $loop = $loop !== null ? $loop : DefaultLoop::instance();
        $loop->fetchDevice(TimerDevice::class)->addTimer($this);
    }

    public function isPeriodic()
    {
        return $this->periodic;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function getCallback()
    {
        return $this->callback;
    }
}
