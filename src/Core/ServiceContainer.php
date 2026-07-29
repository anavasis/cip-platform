<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class ServiceContainer
{
    /** @var array<string, object> */
    private $services = array();

    /** @var array<string, callable> */
    private $factories = array();

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        $key = (string) $id;

        return array_key_exists($key, $this->services) || isset($this->factories[$key]);
    }

    /**
     * @param string $id
     * @return object
     */
    public function get($id)
    {
        $key = (string) $id;

        if (array_key_exists($key, $this->services)) {
            return $this->services[$key];
        }

        if (!isset($this->factories[$key])) {
            throw new \RuntimeException('Service not registered: ' . $key);
        }

        $service = call_user_func($this->factories[$key], $this);

        if (!is_object($service)) {
            throw new \RuntimeException('Service factory did not return an object: ' . $key);
        }

        $this->services[$key] = $service;
        unset($this->factories[$key]);

        return $service;
    }

    /**
     * @param string $id
     * @param object $service
     * @return void
     */
    public function set($id, $service)
    {
        $key = (string) $id;

        if ($this->has($key)) {
            throw new \RuntimeException('Duplicate service registration: ' . $key);
        }

        $this->services[$key] = $service;
    }

    /**
     * @param string $id
     * @param callable $factory
     * @return void
     */
    public function factory($id, $factory)
    {
        $key = (string) $id;

        if ($this->has($key)) {
            throw new \RuntimeException('Duplicate service registration: ' . $key);
        }

        $this->factories[$key] = $factory;
    }
}
