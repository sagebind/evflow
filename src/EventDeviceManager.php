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
class EventDeviceManager implements \IteratorAggregate
{
    /**
     * @var \SplObjectStorage A collection of event devices attached to this event loop.
     */
    protected $devices;

    /**
     * @var LoopInterface The event loop the device manager belongs to.
     */
    protected $loop;

    /**
     * Creates a new event device manager.
     *
     * @param LoopInterface $loop The event loop the device manager belongs to.
     */
    public function __construct(LoopInterface $loop)
    {
        $this->devices = new \SplObjectStorage();
        $this->loop = $loop;
    }

    /**
     * Gets the number of active event devices.
     *
     * @return int
     */
    public function activeDeviceCount()
    {
        $count = 0;
        foreach ($this->devices as $device) {
            if ($device->isActive()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Attaches an event device instance to the event loop.
     *
     * @param EventDeviceInterface $device
     */
    public function attachDevice(EventDeviceInterface $device)
    {
        // attach the device...
        $this->devices->attach($device);
        // ...and set the device's loop context
        $device->setLoop($this->loop);
    }

    /**
     * Detaches an event device from the event loop.
     *
     * @param EventDeviceInterface $device
     */
    public function detachDevice(EventDeviceInterface $device)
    {
        // check if the device exists
        if ($this->devices->contains($device)) {
            // remove loop context
            $device->setLoop(null);
            $this->devices->detach($device);
        }
    }

    /**
     * Gets an attached event device instance of a given type, or creates a new
     * instance of one cannot be found.
     *
     * This is a convenience method that allows abstraction over event device
     * types. An interface can be given as `$type` and any device that implements
     * the interface could be returned. A concrete type can be specified as an
     * alternative to use.
     *
     * @param  string               $type        The type of the event device.
     * @param  string               $defaultType The type to use if an instance of the given type cannot be found.
     * @return EventDeviceInterface              An event device instance.
     *
     * @throws TypeException Thrown if a new instance of a type could not be created.
     */
    public function getDeviceOfType($type, $defaultType = null)
    {
        // find a device of the given type
        foreach ($this->devices as $device) {
            if ($device instanceof $type) {
                return $device;
            }
        }

        // instance not found
        // if no default type is specified, we will try to instantiate the given class
        if ($defaultType === null) {
            $defaultType = $type;
        }

        // check if the type exists
        if (!class_exists($defaultType) && !interface_exists($defaultType)) {
            throw new TypeException("Class or interface \"$defaultType\" does not exist.");
        }

        // check if the type is instantiable
        $class = new \ReflectionClass($defaultType);
        if ($class->isInstantiable()) {
            // attach & return a new instance
            $instance = $class->newInstance();
            $this->attachDevice($instance);
            return $instance;
        }

        // can't instantiate the type
        throw new TypeException("Cannot create instance of type \"$defaultType\".");
    }

    /**
     * Gets an iterator for looping over each event device.
     *
     * @return \Iterator
     */
    public function getIterator()
    {
        return new \IteratorIterator($this->devices);
    }
}
