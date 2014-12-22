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

use React\Promise\Deferred;

include 'vendor/autoload.php';

$w1 = new TimerWatcher(1);
$w1->then(function() use ($w1) {
    printf("Hello 1! Ran at tick %d\n", $w1->getLoop()->getTickCount());
});

$w2 = new TimerWatcher(10);
$w2->then(function() use ($w2) {
    printf("Hello 2! Ran at tick %d\n", $w2->getLoop()->getTickCount());
})
->then(function () {
    print "I was called back after the timer ran!\n";
});

$stream = fopen("/data/Programs/BeamNG-Techdemo-0.3-setup.exe", 'r');
$w3 = new StreamWatcher($stream, StreamWatcher::READ);
$w3->then(function () use ($stream) {
    $x = fread($stream, 256);
    printf("Read %d bytes from file!\n", strlen($x));
});

$w4 = new StreamWatcher(STDIN, StreamWatcher::READ);
$w4->then(function () {
    $x = fgets(STDIN);
    printf("Read async from STDIN: %s", $x);
});


if ($fp = fsockopen('fake-response.appspot.com', 80, $errno, $errstr, 30)) {
    $out = "GET / HTTP/1.1\r\n";
    $out .= "Host: fake-response.appspot.com\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);

    $w5 = new StreamWatcher($fp, StreamWatcher::READ);
    $w5->then(function () use ($fp) {
        echo "\r\nResponse:\r\n", fread($fp, 1024);
        fclose($fp);
    });
}

print "Before event loop!\n";
