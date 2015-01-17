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

namespace Evflow;

/**
 * An interface for event devices that can be polled by an event loop.
 */
interface EventDeviceInterface
{
    /**
     * Gets the event loop context of the device.
     *
     * @return LoopInterface The event loop context.
     */
    public function getLoop();

    /**
     * Sets the event loop context of the device.
     *
     * @param LoopInterface $loop An event loop.
     */
    public function setLoop(LoopInterface $loop);

    /**
     * Polls the event device to process new incoming events.
     *
     * The event device should wait for new events until `$timeout` is reached.
     * If a 0 is given as the timeout, the event device should check for events
     * once and return immediately. If a timeout of -1 is given, the event device
     * should wait indefinitely for new events until at least one occurs.
     *
     * @param int $timeout The poll timeout in microseconds.
     */
    public function poll($timeout);

    /**
     * Checks if the event device is actively listening for events.
     *
     * An event device should be active if it still has more events that will
     * trigger any callbacks. That is, this function should return false only
     * if it is no longer possible for the event device to generate new tasks
     * to be scheduled.
     *
     * @return bool True if the event device is idle, otherwise false.
     */
    public function isActive();
}
