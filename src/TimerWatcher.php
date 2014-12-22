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

class TimerWatcher extends Watcher
{
    private $wakeupAt = 0;

    public function __construct($time, LoopInterface $loop = null)
    {
        parent::__construct($loop);
        $this->wakeupAt = microtime(true) + $time;
    }

    /**
     * [poll description]
     * @return [type] [description]
     *
     * @see http://pod.tst.eu/http://cvs.schmorp.de/libev/ev.pod#The_special_problem_of_being_too_ear
     */
    public function poll()
    {
        return microtime(true) > $this->wakeupAt;
    }

    public function await()
    {
        // sleep until *we* are ready
        usleep($this->wakeupAt - microtime(true));
    }
}
