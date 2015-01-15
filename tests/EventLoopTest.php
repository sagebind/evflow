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

use Evflow;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class EventLoopTest extends \PHPUnit_Framework_TestCase
{
    protected $eventLoop;

    public function setUp()
    {
        $log = new Logger('EventLoop');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $this->eventLoop = new Evflow\EventLoop();
        $this->eventLoop->setLogger($log);
    }

    public function testStreams()
    {
        $device = $this->eventLoop->getDeviceOfType(Evflow\StreamEventDevice::class);

        $ran = false;
        // open a socket
        $socket = fsockopen('fake-response.appspot.com', 80, $errno, $errstr, 30);

        // send an HTTP request
        $out = "GET / HTTP/1.1\r\n";
        $out .= "Host: fake-response.appspot.com\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($socket, $out);

        $device->addStream($socket, Evflow\StreamEventDevice::READ, function ($stream) use (&$ran) {
            fread($stream, 1024);
            fclose($stream);
            $ran = true;
        });

        $this->eventLoop->run();
        $this->assertTrue($ran);
    }
}
