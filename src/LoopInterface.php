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
 * A generic loop interface that supports execution control and a task double queue.
 */
interface LoopInterface
{
    /**
     * Schedules a callback to be executed in the future.
     *
     * This function is typically used to queue up callbacks for asynchronous
     * events, usually from an event device.
     *
     * @param callable $callback
     */
    public function futureTick(callable $callback);

    /**
     * Schedules a callback to be executed immediately in the next tick.
     *
     * This function should be used when a callback needs to be executed later,
     * but needs to do so before any more event callbacks are invoked.
     *
     * @param callable $callback
     */
    public function nextTick(callable $callback);

    /**
     * Attaches an event source to the event loop, with an attached callback.
     *
     * An attached source will begin to be checked for events as soon as the
     * event loop begins ticking.
     *
     * @param Source   $source   The event source object to attach.
     * @param callable $callback A callback to invoke when the source triggers.
     */
    public function attachSource(Source $source, callable $callback);

    /**
     * Detaches an event source from the event loop.
     *
     * @param Source $source The event source to detach.
     */
    public function detachSource(Source $source);

    /**
     * Checks if the event loop is currently running.
     *
     * @return bool True if the event loop is running, otherwise false.
     */
    public function isRunning();

    /**
     * Executes a single iteration of the event loop.
     *
     * @param bool $mayBlock Specifies if the tick is allowed to block the thread
     *                       to wait for events.
     */
    public function tick($mayBlock = false);

    /**
     * Runs the event loop until there are no more events to process.
     */
    public function run();

    /**
     * Stops the event loop execution.
     */
    public function stop();
}
