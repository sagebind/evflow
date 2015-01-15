# Evflow
[![Latest Stable Version](http://img.shields.io/packagist/v/evflow/evflow.svg?style=flat)](https://packagist.org/packages/evflow/evflow)
![Code Quality](https://img.shields.io/scrutinizer/g/coderstephen/evflow.svg?style=flat)
[![License](http://img.shields.io/badge/license-Apache--2.0-b57edc.svg?style=flat)](http://www.apache.org/licenses/LICENSE-2.0)
![Status](http://img.shields.io/badge/status-awesome-blue.svg?style=flat)

Evflow (/ˈɛvfloʊ/) is an open and extensible event loop library for asynchronous programming in PHP. It is taken from many lessons learned from other attempts at asynchronous programming in PHP, especially from [ReactPHP](http://reactphp.org). Evflow is currently an experiment, and will be changed constantly as new ideas take form.

## Usage
Here is how to use the current version of the code if you want to play around.

```php
<?php
use Evflow\DefaultLoop;
use Evflow\StreamEventDevice;

// get a stream event device
$streams = DefaultLoop::instance()->getDeviceOfType(StreamEventDevice::class);

// wait for input on stdin
$streams->addStream(STDIN, StreamEventDevice::READ, function () {
    $line = fgets(STDIN);
    printf("Read async from STDIN: '%s'\r\n", $line);
    fclose(STDIN);
});

// open a slow connection
if ($socket = fsockopen('fake-response.appspot.com', 80, $errno, $errstr, 30)) {
    $out = "GET / HTTP/1.1\r\n";
    $out .= "Host: fake-response.appspot.com\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($socket, $out);

    // wait for response
    $streams->addStream($socket, StreamEventDevice::READ, function () use ($socket) {
        echo "\r\nResponse:\r\n", fread($socket, 1024);
        fclose($socket);
    });
}
```

## Tests
When the project settles in and becomes more stable, unit test coverage will be a top priority, but 100% code coverage will likely not be possible. Asynchronous programming is non-linear and complex, and testing an event loop is not straightforward. We're not there yet, so while you can run the test suite with `phpunit`, don't expect anything to be fully tested. :)

## Uses
Evflow's pluggable architecture opens up tons of integration possibilities. Some asynchronous libraries exist for PHP, but most of them use their own event loops and retrofitting them to use another would be difficult. To make asynchronous programming really work, tasks need to share the same event loop so that processing time is given fairly and correctly to all event callbacks.

Evflow allows you to define your own types of asynchronous events. All that is required is the ability to implement a non-blocking `poll()` method for the event and it will work like magic. There are a number of such functions out there that unfortunately cannot really be used; until now. Some of these possibilities include:

- Async stream I/O
- Async HTTP requests using non-blocking sockets
    + Async web API SDKs using the above
- Async MySQL queries using `mysqli_query()` with `MYSQLI_ASYNC` and `mysqli_poll()`
- Async PostgreSQL using `pg_send_query()` and `pg_connection_busy()`

## Goals
The goal of this project is to (eventually) create a usable, stable, and fast event loop system that is practical and useful in many kinds of applications. Unlike some libraries, Evflow is meant to be only an event library; it should do one thing, and one thing well. Relevant features could be added in the future beyond a basic event loop to make it more useful. Some things that should be or are being looked into are:

- Using generators to make creating arbitrary async functions simpler and more powerful
    + Many ideas could be borrowed from Luke Hoban's excellent [Async Functions for ECMAScript](http://github.com/lukehoban/ecmascript-asyncawait) proposal.
    + Nikita Popov already wrote [an awesome article](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html) on using generators for multitasking. Many raw ideas should be borrowed from him.
- Loop classes that use [libev](http://libev.schmorp.de), [libuv](https://github.com/joyent/libuv), or [libevent](http://libevent.org) as a back-end to implement event handling
- Synchronizing with event loops in other (forked) processes
    + Dealing with forks is easier with Kris Wallsmith's [Spork](https://github.com/kriswallsmith/spork) library.
- Task workers using [pthreads](http://pthreads.org)
- POSIX process signals & interrupts
- [The many excellent ideas](http://wiki.dlang.org/Event_system) the D language community has for their own language; some of which could be borrowed

## License
All Evflow documentation and source code is licensed under the Apache License, Version 2.0 (Apache-2.0). See [LICENSE.md](LICENSE.md) for details.
