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

/**
 * Creates new event loop instances.
 */
class LoopFactory
{
    /**
     * Creates a new event loop instance based on the available extensions.
     *
     * @return LoopInterface A new event loop instance.
     */
    public static function create()
    {
        /* @TODO
        if (extension_loaded('uv')) {
            return new LibUvLoop();
        } elseif (extension_loaded('ev')) {
            return new LibEvLoop();
        } elseif (extension_loaded('event')) {
            return new LibEventLoop();
        } */

        return new SelectLoop();
    }
}
