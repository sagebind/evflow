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

namespace Zephyr\EventLoop;

/**
 * Represents a source of events.
 */
class TimerSource implements Source
{
    protected $interval;
    protected $periodic;
    private $wakeupAt;

    /**
     * Creates a new timer source.
     *
     * @param float $interval The time interval in microseconds.
     * @param bool  $periodic [description]
     */
    public function __construct($interval, $periodic = false)
    {
        $this->interval = $interval;
        $this->periodic = !!$periodic;

        $time = MainLoop::instance()->getTime();
        $this->wakeupAt = $time + $interval;
    }

    public function isPeriodic()
    {
        return $this->periodic;
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function prepare(LoopInterface $loop)
    {
        return max(0, $this->wakeupAt - $loop->getTime());
    }

    public function check(LoopInterface $loop)
    {
        $time = $loop->getTime();
        if ($time > $this->wakeupAt) {
            if ($this->periodic) {
                $this->wakeupAt = $time + $this->interval;
            }

            return true;
        }

        return false;
    }

    public function dispatch(callable $callback)
    {
        call_user_func($callback);

        // Keep the source alive if periodic
        return $this->periodic;
    }
}
