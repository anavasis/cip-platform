<?php

namespace StudyMentor\ContentEngine\Core;

defined('ABSPATH') || exit;

final class ModuleLoader
{
    const STATE_NOT_STARTED = 'not_started';
    const STATE_LOADING = 'loading';
    const STATE_LOADED = 'loaded';
    const STATE_FAILED = 'failed';

    private $registry;
    private $container;
    private $state = self::STATE_NOT_STARTED;

    public function __construct(ModuleRegistry $registry, ServiceContainer $container)
    {
        $this->registry = $registry;
        $this->container = $container;
    }

    /**
     * @return void
     */
    public function load()
    {
        if ($this->state === self::STATE_LOADING) {
            throw new \RuntimeException('ModuleLoader load already in progress');
        }

        if ($this->state === self::STATE_LOADED) {
            throw new \RuntimeException('ModuleLoader already loaded');
        }

        if ($this->state === self::STATE_FAILED) {
            throw new \RuntimeException('ModuleLoader load previously failed');
        }

        $this->state = self::STATE_LOADING;

        try {
            foreach ($this->registry->all() as $module) {
                $module->register($this->container);
            }

            foreach ($this->registry->all() as $module) {
                $module->boot($this->container);
            }

            $this->state = self::STATE_LOADED;
        } catch (\Throwable $exception) {
            $this->state = self::STATE_FAILED;
            throw $exception;
        }
    }

    /**
     * @return string
     */
    public function state()
    {
        return $this->state;
    }
}
