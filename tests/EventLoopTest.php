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

namespace Evflow\Tests;

use Evflow\EventLoop;
use Evflow\TimerWatcher;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class EventLoopTest extends \PHPUnit_Framework_TestCase
{
    protected $eventLoop;

    public function setUp()
    {
        $log = new Logger('EventLoop');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $this->eventLoop = new EventLoop();
        $this->eventLoop->setLogger($log);
    }

    public function testTimer()
    {
        $ran = false;

        $watcher = new TimerWatcher(3, $this->eventLoop);
        $watcher->promise()->then(function ($watcher) use (&$ran) {
            $ran = true;
        });

        $this->eventLoop->run();
        $this->assertTrue($ran);
    }
}
