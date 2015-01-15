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

/**
 * An event device that watches streams for read and write activity.
 */
class StreamEventDevice extends EventDeviceBase
{
    const READ = 1;
    const WRITE = 2;

    protected $readStreams = [];
    protected $writeStreams = [];
    protected $callbacks = [];

    /**
     * Registers interest in status changes on a stream.
     *
     * @param [type] $stream [description]
     * @param [type] $mode   [description]
     */
    public function addStream($stream, $mode, $callback)
    {
        if (($mode & self::READ) === self::READ) {
            $this->readStreams[(int)$stream] = $stream;
        }

        if (($mode & self::WRITE) === self::WRITE) {
            $this->writeStreams[(int)$stream] = $stream;
        }

        $this->callbacks[(int)$stream] = $callback;
    }

    public function removeStream($stream, $mode = 3)
    {
        if (($mode & self::READ) === self::READ && isset($this->readStreams[(int)$stream])) {
            unset($this->readStreams[(int)$stream]);
        }

        if (($mode & self::WRITE) === self::WRITE && isset($this->writeStreams[(int)$stream])) {
            unset($this->writeStreams[(int)$stream]);
        }

        if (isset($this->callbacks[(int)$stream])) {
            unset($this->callbacks[(int)$stream]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function poll($timeout)
    {
        $read = array_values($this->readStreams);
        $write = array_values($this->writeStreams);
        $except = [];

        if (stream_select($read, $write, $except, 0) !== false) {
            foreach ($read as $stream) {
                $this->getLoop()->scheduleTask(function () use ($stream) {
                    $this->callbacks[(int)$stream]($stream);
                    if (!is_resource($stream)) {
                        $this->removeStream($stream);
                    }
                });
            }

            foreach ($write as $stream) {
                $this->getLoop()->scheduleTask(function () use ($stream) {
                    $this->callbacks[(int)$stream]($stream);
                    if (!is_resource($stream)) {
                        $this->removeStream($stream);
                    }
                });
            }
        }

        return count($read) + count($write);
    }

    /**
     * {@inheritDoc}
     */
    public function isActive()
    {
        return count($this->readStreams) + count($this->writeStreams) > 0;
    }
}
