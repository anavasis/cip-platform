<?php

namespace StudyMentor\ContentEngine\Contracts;

use StudyMentor\ContentEngine\Core\ServiceContainer;

defined('ABSPATH') || exit;

interface ModuleInterface
{
    /**
     * @return string
     */
    public function id();

    /**
     * @return void
     */
    public function register(ServiceContainer $container);

    /**
     * @return void
     */
    public function boot(ServiceContainer $container);
}
