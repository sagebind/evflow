<?php
/*
 * Copyright 2014 Stephen Coakley <me@stephencoakley.com>
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

namespace Evflow;

/**
 * An event loop implementation.
 */
class BaseEventLoop implements LoopInterface
{
    protected $lowPriorityQueue;
    protected $highPriorityQueue;

    /**
     * A list of watchers.
     *
     * A linked list here is more efficient than an array since we only iterate
     * over each watcher sequentially and never access arbitrary items.
     *
     * @type \SplDoublyLinkedList
     */
    protected $watchers;

    /**
     * Flag that indicates if the event loop is currently running.
     * @type boolean
     */
    protected $running = false;
    protected $tickCount = 0;

    /**
     * Creates a new event loop instance.
     *
     * The created event loop operates competely independently from the global
     * event loop and other event loop instances.
     */
    public function __construct()
    {
        $this->watchers = new \SplDoublyLinkedList();
    }

    public function addWatcher(Watcher $watcher)
    {
        $this->watchers->push($watcher);
    }

    public function setTimeout($time, callable $callback)
    {
        $this->watchers[] = new TimerWatcher($time, $callback, $this);
    }

    /**
     * [getTickCount description]
     * @return [type] [description]
     */
    public function getTickCount()
    {
        return $this->tickCount;
    }

    public function suspend()
    {
        $this->running = false;
    }

    public function resume()
    {
        $this->running = true;
    }

    public function stop()
    {
        $this->running = false;
    }

    /**
     * Executes a single iteration of the event loop.
     * @return [type] [description]
     */
    public function tick()
    {
        foreach ($this->watchers as $key => $watcher) {
            if ($watcher->poll()) {
                $watcher->callback();

                // O(n); no removeCurrent() which would be O(1) :(
                $this->watchers->offsetUnset($key);
            }
        }
        $this->tickCount++;
    }

    /**
     * Starts the event loop.
     * @return [type] [description]
     */
    public function start()
    {
        $this->running = true;
        while ($this->running) {
            if ($this->watchers->isEmpty()) {
                $this->stop();
            }

            $this->tick();
            usleep(1);
        }
    }
}
