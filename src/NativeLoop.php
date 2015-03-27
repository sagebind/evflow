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
 * The default event loop implementation that has no external dependencies.
 */
class NativeLoop implements LoopInterface, LoggerAwareInterface
{
    /**
     * @var EventDeviceBinder A collection of event devices attached to this event loop.
     */
    protected $devices;

    /**
     * @var \SplQueue A queue of callbacks to be invoked in the next tick.
     */
    private $nextTickQueue;

    /**
     * @var \SplQueue A queue of callbacks to be invoked in a future tick.
     */
    private $futureTickQueue;

    /**
     * @var int The current running tick count.
     */
    private $tickCount = 0;

    /**
     * @var int The minimum allowed time between ticks in microseconds.
     */
    private $minPollInterval = 1000;

    /**
     * @var LoggerInterface A logger for sending log information to.
     */
    private $logger;

    /**
     * @var bool Flag that indicates if the event loop is currently running.
     */
    private $running = false;

    /**
     * @var bool A flag indicating if the event loop is in the idle state.
     */
    private $idle = false;

    /**
     * Creates a new event loop instance.
     *
     * The created event loop operates completely independently from the global
     * event loop and other event loop instances.
     */
    public function __construct()
    {
        $this->nextTickQueue = new \SplQueue();
        $this->futureTickQueue = new \SplQueue();
        $this->devices = new EventDeviceBinder();
    }

    /**
     * {@inheritDoc}
     */
    public function bindDevice(EventDeviceInterface $instance, $type = null)
    {
        $this->devices->bindDevice($instance, $type);
        $this->log(LogLevel::DEBUG, 'New event device of type '.get_class($instance).' bound.');
    }

    public function fetchDevice($type)
    {
        return $this->devices->fetchDevice($type);
    }

    /**
     * Checks if the event loop is in the idle state.
     *
     * The event loop enters the idle state when there are no pending or future
     * callback functions to invoke. There may still be active event devices
     * attached to the loop while it is idle, and will not exit until all event
     * devices are inactive.
     *
     * @return bool
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * {@inheritDoc}
     */
    public function isRunning()
    {
        return $this->running;
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
     * @inheritDoc
     */
    public function nextTick(callable $callback)
    {
        $this->nextTickQueue->enqueue($callback);
        $this->log(LogLevel::INFO, 'Enqueued new next tick callback.');
    }

    /**
     * @inheritDoc
     */
    public function futureTick(callable $callback)
    {
        $this->futureTickQueue->enqueue($callback);
        $this->log(LogLevel::INFO, 'Enqueued new future tick callback.');
    }

    /**
     * @inheritDoc
     */
    public function tick()
    {
        // invoke all callbacks scheduled for this tick
        while (!$this->nextTickQueue->isEmpty()) {
            $callback = $this->nextTickQueue->dequeue();
            $this->log(LogLevel::INFO, 'Invoking next scheduled tick callback.');
            $callback();
        }

        // invoke the next future tick callback
        if (!$this->futureTickQueue->isEmpty()) {
            $callback = $this->futureTickQueue->dequeue();
            $this->log(LogLevel::INFO, 'Invoking next future tick callback.');
            $callback();
        }

        // poll each device & return immediately
        $timeout = 0;

        // if the loop is idle and only one device is active we can poll it forever
        if ($this->devices->activeDeviceCount() === 1 && $this->idle) {
            $timeout = -1;
            $this->log(LogLevel::DEBUG, 'Only one active event device. Polling indefinitely.');
        }

        // poll all event devices for new events
        foreach ($this->devices as $device) {
            // only poll the device if it is active
            if ($device->isActive()) {
                $device->poll($this, $timeout);
            }
        }

        $this->tickCount++;
    }

    /**
     * @inheritDoc
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
            if ($this->nextTickQueue->isEmpty() && $this->futureTickQueue->isEmpty()) {
                if (!$this->idle) {
                    $this->idle = true;
                    $this->log(LogLevel::INFO, 'Entered idle state.');
                }
            } elseif ($this->idle) {
                $this->idle = false;
                $this->log(LogLevel::INFO, 'Leaving idle state.');
            }

            // if we have no more work to do, stop wasting time
            if ($this->idle && $this->devices->activeDeviceCount() === 0) {
                $this->log(LogLevel::DEBUG, 'Nothing left to do.');
                $this->stop();
            }

            usleep($this->minPollInterval);
        }

        $this->log(LogLevel::INFO, 'Event loop stopped.');
    }

    /**
     * @inheritDoc
     */
    public function stop()
    {
        $this->log(LogLevel::DEBUG, 'Stopping event loop.');
        $this->running = false;
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
