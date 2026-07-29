<?php

namespace StudyMentor\ContentEngine\Core;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;

defined('ABSPATH') || exit;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private $modules = array();

    /**
     * @return void
     */
    public function register(ModuleInterface $module)
    {
        $id = $module->id();

        if ($id === '' || isset($this->modules[$id])) {
            throw new \RuntimeException('Duplicate module registration: ' . $id);
        }

        $this->modules[$id] = $module;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->modules[(string) $id]);
    }

    /**
     * @param string $id
     * @return ModuleInterface|null
     */
    public function get($id)
    {
        $key = (string) $id;

        return isset($this->modules[$key]) ? $this->modules[$key] : null;
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function all()
    {
        return $this->modules;
    }
}
