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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * A built-in event loop implementation that abstracts polling types to watchers.
 */
class EventLoop implements LoopInterface, LoggerAwareInterface
{
    /**
     * A collection of event devices attached to this event loop.
     * @type \SplObjectStorage
     */
    protected $devices;

    /**
     * A queue of received event handlers to execute.
     * @type \SplQueue
     */
    protected $eventQueue;

    /**
     * A queue of microtask handlers to execute.
     * @type \SplQueue
     */
    protected $microtaskQueue;

    /**
     * Flag that indicates if the event loop is currently running.
     * @type boolean
     */
    protected $running = false;

    /**
     * A flag indicating if the event loop is in the idle state.
     * @type boolean
     */
    protected $idle = false;

    /**
     * The current running tick count.
     * @type integer
     */
    protected $tickCount = 0;

    /**
     * A logger for sending log information to.
     * @type LoggerInterface
     */
    protected $logger;

    /**
     * Creates a new event loop instance.
     *
     * The created event loop operates completely independently from the global
     * event loop and other event loop instances.
     */
    public function __construct()
    {
        // initialize all the collections
        $this->devices = new \SplObjectStorage();
        $this->eventQueue = new \SplQueue();
        $this->microtaskQueue = new \SplQueue();
    }

    /**
     * Sets a logger instance to send event log info to.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->log(LogLevel::DEBUG, 'New logger registered. Hello logger!');
    }

    /**
     * Schedules a task to be executed in the future.
     *
     * @param callable $task
     */
    public function scheduleTask(callable $task)
    {
        $this->log(LogLevel::DEBUG, 'Adding new callback to event queue.');
        $this->eventQueue->enqueue($task);
        $this->log(LogLevel::INFO, 'Enqueued new event callback.');
    }

    /**
     * Schedules a microtask to be executed immediately in the next tick.
     *
     * @param callable $task
     */
    public function scheduleMicrotask(callable $task)
    {
        $this->log(LogLevel::DEBUG, 'Adding new callback to microtask queue.');
        $this->microtaskQueue->enqueue($task);
        $this->log(LogLevel::INFO, 'Enqueued new microtask callback.');
    }

    /**
     * Attaches an event device instance to the event loop.
     *
     * @param EventDeviceInterface $device
     */
    public function attachDevice(EventDeviceInterface $device)
    {
        $this->devices->attach($device, spl_object_hash($device));
        $this->log(LogLevel::INFO, 'New event device #' . spl_object_hash($device) . ' attached.');
    }

    /**
     * Detaches an event device from the event loop.
     *
     * @param EventDeviceInterface $device
     */
    public function detachDevice(EventDeviceInterface $device)
    {
        if ($this->devices->contains($device)) {
            $this->devices->detach($device);
            $this->log(LogLevel::INFO, 'Unloaded event device #' . spl_object_hash($device) . '\'.');
        }
    }

    public function hasDeviceOfType($deviceType)
    {
        foreach ($this->devices as $device) {
            if ($device instanceof $deviceType) {
                return true;
            }
        }
    }

    public function getDeviceOfType($waiterType)
    {
        foreach ($this->devices as $device) {
            if ($device instanceof $deviceType) {
                return $device;
            }
        }
    }

    /**
     * Checks if the event loop is in the idle state.
     *
     * @return boolean
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * Gets the current tick count of the event loop.
     *
     * The tick count is an integer that indicates how many iterations the event
     * loop has made since it started. The first tick begins at tick 0. The tick
     * count is not reset if the event loop is stopped and resumed multiple
     * times.
     *
     * @return int
     */
    public function getTickCount()
    {
        return $this->tickCount;
    }

    /**
     * Checks if the event loop or any event devices will have any more work to
     * do in the future.
     *
     * Helps you find those lazy peons.
     *
     * @return boolean
     */
    public function isActive()
    {
        // check the microtask queue first
        if (!$this->microtaskQueue->isEmpty()) {
            return true;
        }

        // any event callbacks?
        if (!$this->eventQueue->isEmpty()) {
            return true;
        }

        // check each event device
        foreach ($this->devices as $device) {
            if ($device->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function stop()
    {
        $this->log(LogLevel::DEBUG, 'Stopping event loop.');
        $this->running = false;
    }

    /**
     * {@inheritDoc}
     */
    public function tick()
    {
        // execute all microtasks
        while (!$this->microtaskQueue->isEmpty()) {
            $this->microtaskQueue->dequeue();
        }

        // execute the next event task
        if (!$this->eventQueue->isEmpty()) {
            $this->log(LogLevel::INFO, 'Dequeued event callback from event queue.');
            $task = $this->eventQueue->dequeue();
            $this->log(LogLevel::INFO, 'Invoking event callback.');
            $task();
        }

        // poll all event devices for new events
        foreach ($this->devices as $device) {
            // only poll the device if it is active
            if ($device->isActive()) {
                $readyCount = $device->poll(0);

                if ($readyCount > 0) {
                    $this->log(LogLevel::INFO, $readyCount . ' event(s) detected from device #' . spl_object_hash($device) . '.');
                }
            }
        }

        $this->tickCount++;
    }

    /**
     * {@inheritDoc}
     */
    public function run()
    {
        $this->running = true;
        $this->log(LogLevel::INFO, 'Event loop started.');

        // run the event loop until instructed otherwise
        while ($this->running) {
            // execute a single tick
            $this->tick();

            // update idle state
            $this->updateIdleState();

            // if we have no more work to do, stop wasting time
            if (!$this->isActive()) {
                $this->log(LogLevel::DEBUG, 'Nothing left to do.');
                $this->stop();
            }

            usleep(1);
        }

        $this->log(LogLevel::INFO, 'Event loop stopped.');
    }

    /**
     * Updates the idle state information by checking if there are any pending
     * tasks.
     */
    protected function updateIdleState()
    {
        if ($this->eventQueue->isEmpty() && $this->microtaskQueue->isEmpty()) {
            if (!$this->idle) {
                $this->idle = true;
                $this->log(LogLevel::INFO, 'Entered idle state.');
            }
        } elseif ($this->idle) {
            $this->idle = false;
            $this->log(LogLevel::INFO, 'Leaving idle state.');
        }
    }

    /**
     * Outputs a logging message to a configured logger, if any.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    protected function log($level, $message, array $context = array())
    {
        if ($this->logger instanceof LoggerInterface) {
            $context['tick'] = $this->getTickCount();
            $this->logger->log($level, $message, $context);
        }
    }
}
