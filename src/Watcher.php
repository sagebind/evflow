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

use React\Promise\PromiseInterface;
use React\Promise\Deferred;

/**
 * An object that polls for some event and indicates to an event loop your interest in the event.
 */
abstract class Watcher implements PromiseInterface
{
    protected $loop;
    protected $callback;
    protected $active = true;
    protected $priority;
    protected $deferred;

    public function __construct(LoopInterface $loop = null)
    {
        $this->deferred = new Deferred();
        $this->loop = $loop ? $loop : DefaultLoop::instance();
        //$this->callback = $callback;
        $this->loop->addWatcher($this);
    }

    public function isActive()
    {
        return $this->active;
    }

    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * Polls the watcher to see if its event of interest has occurred.
     *
     * This function *must* be non-blocking. Its only job is to return a boolean
     * immediately that indicates if the event of interest occurred yet. If this
     * function blocks for any reason the contract with the event loop is broken
     * and the whole thing is interrupted.
     *
     * @return boolean
     */
    abstract public function poll();

    public function callback()
    {
        //call_user_func($this->callback, $this);
        $this->deferred->resolve();
    }

    /**
     * Blocks the execution flow until the watcher's event occurs.
     *
     * Useful when a certain value is absolutely necessary for continuing
     * execution. Use sparingly.
     *
     * @return [type] [description]
     */
    //public abstract function await();

    public function promise()
    {
        return $this->deferred->promise();
    }

    /**
     * @return PromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        return $this->deferred->promise()->then($onFulfilled, $onRejected, $onProgress);
    }
}
