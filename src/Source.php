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
 * Represents a generic source of events for an event loop to check for.
 */
interface Source
{
    /**
     * Method that is called before the source is polled.
     *
     * This method is called by an event loop before polling for new events. The
     * method should return a timeout value in microseconds to indicate the
     * maximum delay that should be allowed before the source is polled again.
     * Sources that rely on external events, like IO or signals, can return -1
     * to indicate no timeout is required. If all sources attached to an event
     * loop return -1, the loop will block indefinitely to wait for new events.
     *
     * When the event loop polls for IO, it will use the minimum timeout
     * returned from sources to ensure all sources are checked in a timely
     * manner. A source that needs to be checked repeatedly may return a value
     * of 0 to ensure polling never blocks.
     *
     * @param LoopInterface $loop The loop preparing the source.
     *
     * @return int A maximum timeout in microseconds.
     */
    public function prepare(LoopInterface $loop);

    /**
     * Checks the source for new events.
     *
     * This method should check if new events have been received for the event
     * source and return `true` if the source is ready to be dispatched.
     *
     * @param LoopInterface $loop The loop checking the source.
     *
     * @return bool Indicates if the source should be dispatched.
     */
    public function check(LoopInterface $loop);

    /**
     * Dispatches a pending event callback.
     *
     * This method is called by the event loop sometime in the future after
     * `check()` is called and returns `true`. The method is passed a callback
     * that was specified when the source was attached to an event loop.
     * `dispatch()` should invoke the callback after handling the event and
     * should pass whatever data is necessary for the event in the callback
     * parameters.
     *
     * Dispatch implementations should return a boolean to indicate if the
     * source should be kept alive after triggering. If `dispatch()` returns
     * `false`, the source will be detached from the event loop and discarded.
     *
     * @param callable $callback The event callback to dispatch.
     *
     * @return bool Indicates if the source should be kept alive.
     *
     * @see LoopInterface::attachSource() The source of the callback that is dispatched.
     */
    public function dispatch(callable $callback);
}
