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

use Evenement\EventEmitterTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * A built-in event loop implementation that abstracts polling types to watchers.
 */
class EventLoop implements LoopInterface, LoggerAwareInterface
{
    use EventEmitterTrait;

    /**
     * A list of watchers.
     * @type array
     */
    protected $watchers;

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
        $this->watchers = [];
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
     * Adds an event watcher to the event loop.
     *
     * @param WatcherInterface $watcher
     */
    public function addWatcher(WatcherInterface $watcher)
    {
        $this->watchers[spl_object_hash($watcher)] = $watcher;
        $this->log(LogLevel::INFO, 'New watcher of type \'' . get_class($watcher) . '\' added to queue.');
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

        foreach ($this->watchers as $key => $watcher) {
            if ($watcher->poll()) {
                $this->log(LogLevel::INFO, 'Event detected from watcher #' . $key . '.');
                $this->scheduleTask(function () use ($watcher) {
                    $watcher->resolve();
                });

                unset($this->watchers[$key]);
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
        $this->log(LogLevel::DEBUG, 'Event loop started.');
        $this->emit('startup');
        $this->log(LogLevel::INFO, 'Waiting for events.');

        // run the event loop until instructed otherwise
        while ($this->running) {
            // execute a single tick
            $this->tick();

            // update idle state
            $this->updateIdleState();

            // if we have no pending watchers, stop
            if (count($this->watchers) === 0 && $this->eventQueue->isEmpty()) {
                $this->log(LogLevel::DEBUG, 'Watcher queue is empty.');
                $this->stop();
            }

            usleep(1);
        }

        $this->log(LogLevel::DEBUG, 'Event loop shutting down.');
        $this->emit('shutdown');
        $this->log(LogLevel::INFO, 'Event loop has shut down.');
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
                $this->emit('idle');
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
