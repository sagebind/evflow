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

class StreamWatcher extends Watcher
{
    const READ = 1;
    const WRITE = 2;

    protected $stream;
    protected $read = [];
    protected $write = [];
    protected $mode;

    public function __construct($stream, $mode, LoopInterface $loop = null)
    {
        parent::__construct($loop);

        // ensure stream is set to non-blocking mode
        stream_set_blocking($stream, 0);

        $this->stream = $stream;

        if ($mode & self::READ) {
            $this->read[] = $stream;
        }
        if ($mode & self::WRITE) {
            $this->write[] = $stream;
        }
    }

    public function poll()
    {
        $read = $this->read;
        $write = $this->write;
        $except = null;

        return stream_select($read, $write, $except, 0, 0) > 0;
    }
}
