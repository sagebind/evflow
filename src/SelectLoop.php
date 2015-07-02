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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * The default event loop implementation that has no external dependencies.
 */
class SelectLoop implements LoopInterface, LoggerAwareInterface
{
    const READ = 1;
    const WRITE = 2;
    const MICROSECONDS_PER_SECOND = 1e6;

    /**
     * @var \SplObjectStorage
     */
    private $sources;

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
    private $tickQuantum = 1000;

    /**
     * @var float The current atomic time in microseconds.
     */
    private $currentTime;

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
     * @var array An array of streams to poll for reading.
     */
    protected $readStreams = [];

    /**
     * @var array An array of streams to poll for writing.
     */
    protected $writeStreams = [];

    private $signalRefCounts = [];
    private $signalSources = [];

    /**
     * Creates a new event loop instance.
     *
     * The created event loop operates completely independently from the global
     * event loop and other event loop instances.
     */
    public function __construct($tickQuantum = 1000)
    {
        $this->nextTickQueue = new \SplQueue();
        $this->futureTickQueue = new \SplQueue();
        $this->sources = new \SplObjectStorage();
        $this->tickQuantum = $tickQuantum;
    }

    /**
     * {@inheritdoc}
     */
    public function attachSource(Source $source, callable $callback)
    {
        $this->sources->attach($source, $callback);

        if ($source instanceof SignalSource) {
            $signal = $source->getSignal();

            if (!isset($this->signalSources[$signal])) {
                $this->signalSources[$signal] = new \SplObjectStorage();
            }
            $this->signalSources[$signal]->attach($source);

            $this->incrementSignalRefCount($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detachSource(Source $source)
    {
        $this->sources->detach($source);

        if ($source instanceof SignalSource) {
            $signal = $source->getSignal();
            $this->signalSources[$signal]->detach($source);
            $this->decrementSignalRefCount($signal);
        }
    }

    /**
     * Gets the current time according to the event loop.
     *
     * @return float The current time in microseconds.
     */
    public function getTime()
    {
        if (!$this->currentTime) {
            $this->updateTime();
        }
        return $this->currentTime;
    }

    /**
     * Checks if the event loop is in the idle state.
     *
     * The event loop enters the idle state when there are no pending or future
     * callback functions to invoke. There may still be active event sources
     * attached to the loop while it is idle, and will not exit until all event
     * sources are detached.
     *
     * @return bool
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * {@inheritdoc}
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
     * This function should not be used for timing purposes. Time between ticks
     * is not defined and can vary wildly depending on how long the event loop
     * blocks. Meaning, if the event loop blocks for 1 minute, ticks will not be
     * compensated for the lost time and the 1 minute block will count as a
     * single tick.
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
     * {@inheritdoc}
     */
    public function nextTick(callable $callback)
    {
        $this->nextTickQueue->enqueue($callback);
        $this->log(LogLevel::INFO, 'Enqueued new next tick callback.');
    }

    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $callback)
    {
        $this->futureTickQueue->enqueue($callback);
        $this->log(LogLevel::INFO, 'Enqueued new future tick callback.');
    }

    /**
     * {@inheritdoc}
     */
    public function tick($mayBlock = false)
    {
        // Invoke all callbacks scheduled for this tick
        while (!$this->nextTickQueue->isEmpty()) {
            $callback = $this->nextTickQueue->dequeue();
            $this->log(LogLevel::INFO, 'Invoking next scheduled tick callback.');
            $callback();
        }

        // Invoke the next future tick callback
        if (!$this->futureTickQueue->isEmpty()) {
            $callback = $this->futureTickQueue->dequeue();
            $this->log(LogLevel::INFO, 'Invoking next future tick callback.');
            $callback();
        }

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

        $this->updateTime();

        // If there are any attached event sources, we need to prepare them and
        // block accordingly to wait for events.
        if ($this->sources->count() > 0) {
            // Determine the longest allowed timeout, defaulting to forever (-1).
            $timeout = -1;

            $this->log(LogLevel::DEBUG, 'Preparing '.$this->sources->count().' event sources.');
            foreach ($this->sources as $source) {
                $allowedBlock = $source->prepare($this);

                if ($timeout < 0 || $allowedBlock < $timeout) {
                    $timeout = $allowedBlock;
                }
            }

            if ($timeout >= 0) {
                $timeout = max($timeout, $this->tickQuantum);
                $this->log(LogLevel::DEBUG, 'Blocking to wait for events for at most '.$timeout.' microseconds.');
                $this->poll($timeout);
            } else {
                $this->log(LogLevel::DEBUG, 'Only one active event device. Polling indefinitely.');
                sleep(100);
            }

            $this->updateTime();
        }

        // Process signals are delivered asynchronously and interrupt our block,
        // so we need to check if we were interrupted and invoke any relevant
        // signal callbacks.
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // Now poll all event sources to see if any have triggered since blocking.
        foreach ($this->sources as $source) {
            if ($source->check($this)) {
                $this->log(LogLevel::INFO, 'Scheduling callback for source '.spl_object_hash($source).'.');
                $this->futureTick(function () use ($source) {
                    $callback = $this->sources[$source];
                    if (!$source->dispatch($callback)) {
                        $this->detachSource($source);
                    }
                });
            }
        }

        ++$this->tickCount;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->running = true;
        $this->log(LogLevel::INFO, 'Event loop started.');

        // Run the event loop until instructed otherwise
        while ($this->running) {
            // If we have no more work to do, stop wasting time
            if ($this->idle && $this->sources->count() === 0) {
                $this->log(LogLevel::DEBUG, 'Nothing left to do.');
                $this->stop();
            }

            // execute a single tick
            $this->tick(true);
        }

        $this->log(LogLevel::INFO, 'Event loop stopped.');
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->log(LogLevel::DEBUG, 'Stopping event loop.');
        $this->running = false;
    }

    private function poll($timeout)
    {
        $read = array_values($this->readStreams);
        $write = array_values($this->writeStreams);
        $except = null;

        if (count($read) + count($write) > 0) {
            $seconds = (int)($timeout / 1e6);
            $microseconds = (int)($timeout % 1e6);

            $error = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($error !== false) {
                foreach (array_merge($read, $write) as $stream) {
                    $this->futureTick(function () use ($stream) {
                        $this->callbacks[(int)$stream]($stream);
                        if (!is_resource($stream)) {
                            $this->removeStream($stream);
                        }
                    });
                }
            }
            return true;
        }

        // If we have no streams to poll, we need to block the loop for the
        // given timeout by sleeping to prevent wasting CPU cycles.
        if ($timeout > 0) {
            $seconds = (int)($timeout / 1e6);
            $nanoseconds = (int)($timeout % 1e6) * 1000;

            time_nanosleep($seconds, $nanoseconds);
        }
    }

    /**
     * Registers interest in status changes on a stream.
     *
     * @param resource $stream   A stream resource to watch for status changes.
     * @param int      $mode     The stream modes to watch for.
     * @param callable $callback A callback to invoke when an event on the stream occurs.
     */
    public function addStream($stream, $mode, $callback)
    {
        if (($mode & self::READ) === self::READ) {
            $this->readStreams[(int)$stream] = $stream;
        }

        if (($mode & self::WRITE) === self::WRITE) {
            $this->writeStreams[(int)$stream] = $stream;
        }

        $this->callbacks[(int)$stream] = $callback;
    }

    /**
     * Removes a stream from the device, stopping any listening.
     *
     * @param resource $stream The stream to remove.
     * @param int      $mode   The modes to stop watching for.
     */
    public function removeStream($stream, $mode = 3)
    {
        if (($mode & self::READ) === self::READ && isset($this->readStreams[(int)$stream])) {
            unset($this->readStreams[(int)$stream]);
        }

        if (($mode & self::WRITE) === self::WRITE && isset($this->writeStreams[(int)$stream])) {
            unset($this->writeStreams[(int)$stream]);
        }

        if (isset($this->callbacks[(int)$stream])) {
            unset($this->callbacks[(int)$stream]);
        }
    }

    private function getSignalRefCount($signal)
    {
        if (!isset($this->signalRefCounts[$signal])) {
            $this->signalRefCounts[$signal] = 0;
            return 0;
        }

        return $this->signalRefCounts[$signal];
    }

    private function incrementSignalRefCount($signal)
    {
        // If the signal reference count is at zero, then we need to set a
        // global signal handler for the signal so we can handle it when we are
        // interrupted by it asynchronously. The signal handler will dispatch to
        // any event sources waiting for the signal.
        if ($this->getSignalRefCount($signal) === 0) {
            pcntl_signal($signal, function ($signal) {
                $this->handleSignal($signal);
            });
        }

        ++$this->signalRefCounts[$signal];
    }

    private function decrementSignalRefCount($signal)
    {
        // If we are about to remove the last reference to the signal, we need
        // to remove the global signal handler so that we restore the default
        // behavior, otherwise the signal will be completely ignored.
        if ($this->getSignalRefCount($signal) === 1) {
            pcntl_signal($signal, SIG_DFL);
        }

        --$this->signalRefCounts[$signal];
    }

    private function handleSignal($signal)
    {
        if (isset($this->signalSources[$signal])) {
            $this->nextTick(function () use ($signal) {
                foreach ($this->signalSources[$signal] as $source) {
                    call_user_func($this->sources[$source]);
                }
            });
        }
    }

    /**
     * Updates the internal clock.
     */
    protected function updateTime()
    {
        $this->currentTime = microtime(true) * self::MICROSECONDS_PER_SECOND;
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
        if ($this->logger) {
            $context['tick'] = $this->tickCount;
            $this->logger->log($level, $message, $context);
        }
    }
}
