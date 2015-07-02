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
 * Facade for accessing the global event loop.
 */
final class MainLoop
{
    /**
     * @var LoopInterface The event loop instance being used as the main loop.
     */
    private static $loopInstance;

    /**
     * @var bool Indicates autostart is enabled.
     */
    private static $autoStart = true;

    /**
     * Initializes the global event loop.
     *
     * By default the event loop will register itself to run just before the
     * program exits. If this method is never called (and thus the loop never
     * used), then the loop will never get registered to run and won't disturb
     * normal execution flow at all.
     *
     * @param LoopInterface $loop The loop instance to use as the global event loop.
     */
    public static function init(LoopInterface $loop = null)
    {
        // check if the loop was already initialized
        if (self::$loopInstance) {
            throw new LoopInitializedException('Loop already initialized.');
        }

        // use the given instance, or create a new one if none given
        self::$loopInstance = !!$loop ? $loop : LoopFactory::create();

        // run the global event loop just before the program exits
        register_shutdown_function(function () {
            if (self::$autoStart) {
                self::run();
            }
        });
    }

    /**
     * Gets the default event loop instance being used.
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
     * Enables the automatic execution of the event loop at the end of the current thread.
     *
     * if enabled, the event loop will automatically begin running at the end of
     * the current process' main code execution.
     */
    public static function enableAutoStart()
    {
        self::$autoStart = true;
    }

    /**
     * Disables the automatic execution of the event loop at the end of the current thread.
     */
    public static function disableAutoStart()
    {
        self::$autoStart = false;
    }

    /**
     * Checks if the event loop is currently running.
     *
     * @return bool True if the event loop is running, otherwise false.
     */
    public static function isRunning()
    {
        return self::instance()->isRunning();
    }

    /**
     * Executes a single iteration of the event loop.
     *
     * @param bool $mayBlock Specifies if the tick is allowed to block the thread
     *                       to wait for events.
     */
    public static function tick($mayBlock = false)
    {
        self::instance()->tick($mayBlock);
    }

    /**
     * Runs all tasks in the global event loop.
     */
    public static function run()
    {
        self::instance()->run();
    }

    /**
     * Stops the event loop execution.
     */
    public static function stop()
    {
        self::instance()->stop();
    }

    // Prevents= instantiation.
    private function __construct()
    {
    }
}

class LoopInitializedException extends \LogicException implements Exception
{
}
