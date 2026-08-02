<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintValidator;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;

defined('ABSPATH') || exit;

/**
 * Registers Content Blueprint domain services (BUILD-001).
 * No UI, persistence adapter, AI, or workflow wiring.
 */
final class BlueprintModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'blueprint';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(ContentBlueprintBuilder::class)) {
            $container->set(ContentBlueprintBuilder::class, new ContentBlueprintBuilder());
        }

        if (!$container->has(ContentBlueprintValidator::class)) {
            $container->set(ContentBlueprintValidator::class, new ContentBlueprintValidator());
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
