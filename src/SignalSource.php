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

class SignalSource implements Source
{
    protected $signal;

    public function __construct($signal)
    {
        $this->signal = $signal;
    }

    public function prepare(LoopInterface $loop)
    {
        return -1;
    }

    public function poll(LoopInterface $loop)
    {
        return false;
    }

    public function getSignal()
    {
        return $this->signal;
    }
}
