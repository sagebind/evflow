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

abstract class EventDeviceBase implements EventDeviceInterface
{
    private $loop;

    public static function instance(LoopInterface $loop = null)
    {
        $loop = $loop !== null ? $loop : DefaultLoop::instance();

        if ($loop->hasWaiterOfType(static::class)) {
            return $loop->getWaiterOfType(static::class);
        } else {
            $instance = new static($loop);
            $loop->loadWaiter($instance);
            return $instance;
        }
    }

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function getLoop()
    {
        return $this->loop;
    }
}
