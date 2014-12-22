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

/**
 * Singleton static class that manages a default, global event loop.
 */
final class DefaultLoop
{
    /**
     * The event loop instance being used as the default loop.
     * @type LoopInterface
     */
    private static $loopInstance;

    /**
     * Initializes the global event loop.
     *
     * By default the event loop will register itself to run just before the
     * program exits. If this method is never called (and thus the loop never
     * used), then the loop will never get registered to run and won't disturb
     * normal execution flow at all.
     *
     * @param  LoopInterface $loop [description]
     * @return [type]              [description]
     */
    public static function init(LoopInterface $loop = null)
    {
        self::$loopInstance = !!$loop ? $loop : new BaseEventLoop();

        // run the global event loop just before the program exits
        register_shutdown_function([__CLASS__, 'run']);
    }

    /**
     * Gets the default eent loop instance being used.
     *
     * If the event loop has not been initialized it will be initialized with
     * default values.
     *
     * @return LoopInterface
     */
    public static function instance()
    {
        if (!self::$loopInstance) {
            self::init();
        }
        return self::$loopInstance;
    }

    /**
     * Runs all tasks in the global event loop.
     *
     * @return [type] [description]
     */
    public static function run()
    {
        self::instance()->start();
    }

    // prevents instantiation
    private function __construct()
    {
    }
}
