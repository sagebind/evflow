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

use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Creates and returns a promise to call a generator function asynchronously.
 *
 * @param  callable $function
 * @return Promise
 */
function async(callable $function)
{
    // return a new promise
    return new Promise(function (callable $resolve, callable $reject, callable $progress) use ($function) {
        // get the generator
        $generator = $function($this);

        // function to step the generator to the next yield statement
        $step = function () use (&$step, $generator, $resolve, $reject, $progress) {
            $value = null;

            // try to execute the next block of code
            try {
                $value = $generator->current();
            } catch (\Exception $exception) {
                // exception rejects the promise
                $reject($exception);
                return;
            }

            // if the generator is complete, resolve the promise
            if (!$generator->valid()) {
                $resolve($value);
                return;
            }

            // wrap the value in a promise if it isn't already one
            if (!($value instanceof PromiseInterface)) {
                $value = new FulfilledPromise($value);
            }

            // run the next step when the current one resolves
            $value->then(function ($value) {
                $generator->send($value);
                $step();
            }, function (\Exception $reason) {
                $generator->throw($reason);
            });
        };

        // run the first step in the event loop
        DefaultLoop::instance()->scheduleTask($step);
    });
}
