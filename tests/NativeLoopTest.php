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

use Evflow\NativeLoop;
use Evflow\StreamEventDevice;
use Evflow\Timers\Timer;
use Evflow\Timers\TimerDevice;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class NativeLoopTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var NativeLoop A native loop instance to test.
     */
    protected $loop;

    public function setUp()
    {
        fwrite(STDOUT, PHP_EOL);

        // set up logging so we can ses what is going on
        $log = new Logger('EventLoop');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        // create a loop instance and set the logger
        $this->loop = new NativeLoop();
        $this->loop->setLogger($log);
    }

    public function testTimer()
    {
        // bind the timer device
        $this->loop->bindDevice(new TimerDevice);
        $ran = false;

        $timer = new Timer(1000000, function () use (&$ran) {
            $ran = !$ran;
        }, false, $this->loop);

        $this->loop->run();
        $this->assertTrue($ran);
    }

    public function testStreams()
    {
        $this->loop->bindDevice(new StreamEventDevice);
        $device = $this->loop->fetchDevice(StreamEventDevice::class);

        $ran = false;
        // open a socket
        $socket = fsockopen('fake-response.appspot.com', 80, $errno, $errstr, 30);

        // send an HTTP request
        $out = "GET / HTTP/1.1\r\n";
        $out .= "Host: fake-response.appspot.com\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($socket, $out);

        $device->addStream($socket, StreamEventDevice::READ, function ($stream) use (&$ran) {
            fread($stream, 1024);
            fclose($stream);
            $ran = true;
        });

        $this->loop->run();
        $this->assertTrue($ran);
    }
}
