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
 * Manages a group of event devices on behalf of an event loop.
 */
class EventDeviceBinder implements \IteratorAggregate
{
    /**
     * @var array A map of device types to the instances bound to them.
     */
    protected $deviceBindings = [];

    /**
     * Gets the number of active event devices.
     *
     * @return int
     */
    public function activeDeviceCount()
    {
        $count = 0;
        foreach ($this->deviceBindings as $device) {
            if ($device->isActive()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Binds an event device instance to a type, or to itself if no
     * interface is specified.
     *
     * @param EventDeviceInterface $device The object instance to bind.
     * @param string               $type   The type name to bind to.
     */
    public function bindDevice(EventDeviceInterface $instance, $type = null)
    {
        if ($type === null) {
            $type = get_class($instance);
        } else {
            // check if the given instance implements the interface being bound to
            if (!($instance instanceof $type)) {
                throw new TypeException('Given instance does not implement the class or interface "'.$type.'".');
            }

            // check if the type or class exists
            if (!class_exists($type) && !interface_exists($type)) {
                throw new TypeException('The class or interface "'.$type.'" does not exist.');
            }
        }

        $this->deviceBindings[$type] = $instance;
    }

    /**
     * Gets an attached event device instance of a given type, or creates a new
     * instance of one cannot be found.
     *
     * @param string $type        The type of the event device.
     * @param string $defaultType The type to use if an instance of the given type cannot be found.
     *
     * @return EventDeviceInterface An event device instance.
     *
     * @throws TypeException Thrown if a new instance of a type could not be created.
     */
    public function fetchDevice($type)
    {
        if (isset($this->deviceBindings[$type])) {
            return $this->deviceBindings[$type];
        }

        throw new \Exception('No instance for "'.$type.'" bound.');
    }

    /**
     * Unbinds a device instance from a type if bound.
     *
     * @param string $type The type name to unbind.
     */
    public function unbindDevice($type)
    {
        // check if the device exists
        if (isset($this->deviceBindings[$type])) {
            unset($this->deviceBindings[$type]);
        }
    }

    /**
     * Gets an iterator for looping over each event device.
     *
     * @return \Iterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->deviceBindings);
    }
}
