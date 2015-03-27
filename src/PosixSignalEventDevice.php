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
 * Event device that captures POSIX process signals.
 *
 * @todo Old and messed up. Needs a-fixin'.
 */
class PosixSignalEventDevice implements EventDeviceInterface
{
    protected static $signalRefCount = [];

    protected $signal;

    public function __construct($signal, LoopInterface $loop = null)
    {
        parent::__construct($loop);
        $this->signal = $signal;
        $this->incrementRefCount($signal);
    }

    public function poll($timeout)
    {
        return pcntl_sigtimedwait([$this->signal], $siginfo, 0, 0) === $this->signal;
    }

    public function __destruct()
    {
        $this->decrementRefCount($this->signal);
    }

    protected function getRefCount($signal)
    {
        if (isset(self::$signalRefCount[$signal])) {
            return self::$signalRefCount[$signal];
        } else {
            return 0;
        }
    }

    protected function incrementRefCount($signal)
    {
        self::$signalRefCount[$signal] = $this->getRefCount($signal) + 1;

        if ($this->getRefCount($signal) === 1) {
            pcntl_sigprocmask(SIG_BLOCK, [$signal]);
        }
    }

    protected function decrementRefCount($signal)
    {
        if ($this->getRefCount($signal) > 0) {
            self::$signalRefCount[$signal] = $this->getRefCount($signal) - 1;
        }

        if ($this->getRefCount($signal) === 0) {
            pcntl_sigprocmask(SIG_UNBLOCK, [$signal]);
        }
    }
}
