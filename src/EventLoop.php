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
     * @var \SplQueue A queue of callbacks to be invoked in the next tick.
     */
    protected $nextTickQueue;

    /**
     * @var \SplQueue A queue of callbacks to be invoked in a future tick.
     */
    protected $futureTickQueue;

    /**
     * The current running tick count.
     * @type integer
     */
    protected $tickCount = 0;

    /**
     * The minimum allowed time between ticks in microseconds.
     * @type integer
     */
    protected $minPollInterval = 1;

    /**
     * A logger for sending log information to.
     * @type LoggerInterface
     */
    private $logger;

    /**
     * Flag that indicates if the event loop is currently running.
     * @type boolean
     */
    private $running = false;

    /**
     * A flag indicating if the event loop is in the idle state.
     * @type boolean
     */
    private $idle = false;

    private $currentTime;

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
        $this->devices = new EventDeviceManager($this);
    }

    public function getDevices()
    {
        return $this->devices;
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

    public function updateTime()
    {
        $this->currentTime = microtime(true) * self::MICROSECONDS_PER_SECOND;
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

            usleep(1);
        }

        $this->log(LogLevel::INFO, 'Event loop stopped.');
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
