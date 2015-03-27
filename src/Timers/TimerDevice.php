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

use Evflow\EventDeviceInterface;
use Evflow\LoopInterface;

/**
 * Event device for scheduling timers to be triggered at certain time intervals.
 */
class TimerDevice implements EventDeviceInterface
{
    const MICROSECONDS_PER_SECOND = 1000000;

    private $timers;
    private $timerQueue;
    private $currentTime;

    /**
     * Creates a new timer event device instance.
     */
    public function __construct()
    {
        $this->timers = new \SplObjectStorage();
        $this->timerQueue = new \SplPriorityQueue();
    }

    public function addTimer(Timer $timer)
    {
        $this->updateTime();
        $callbackTime = $this->currentTime + $timer->getInterval();
        $this->timers->attach($timer, $callbackTime);
        $this->timerQueue->insert($timer, -$callbackTime);
    }

    public function updateTime()
    {
        $this->currentTime = microtime(true) * self::MICROSECONDS_PER_SECOND;
    }

    /**
     * Polls the event device to process new incoming events.
     *
     * @see http://pod.tst.eu/http://cvs.schmorp.de/libev/ev.pod#The_special_problem_of_being_too_ear
     */
    public function poll(LoopInterface $loop, $timeout)
    {
        // update internal clock
        $this->updateTime();

        if ($timeout !== 0) {
            $nextTimer = $this->timerQueue->top();
            $timeUntilNextTimer = $this->timers[$nextTimer] - $this->currentTime + 1;
            $sleepAmount = max(0, $timeUntilNextTimer);

            if ($timeout > 0) {
                $sleepAmount = min($timeout, $sleepAmount);
            }

            usleep($sleepAmount);
        }

        // check for ready timers
        while (!$this->timerQueue->isEmpty()) {
            $timer = $this->timerQueue->top();

            // if the target time has passed, call the callback
            if ($this->currentTime > $this->timers[$timer]) {
                // add callback to future tick queue
                $loop->futureTick(function () use ($timer) {
                    $callback = $timer->getCallback();
                    $callback();
                });

                // remove timer from device
                //if (!$timer->isPeriodic()) {
                    $this->timers->detach($timer);
                    $this->timerQueue->extract();
                //}
            } else {
                break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function isActive()
    {
        return $this->timers->count() > 0;
    }
}
