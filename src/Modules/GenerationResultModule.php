<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultBuilder;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultValidator;

defined('ABSPATH') || exit;

/**
 * Registers Generation Result domain services (BUILD-005).
 * No providers, HTTP, queues, workers, persistence, UI, or publishing.
 */
final class GenerationResultModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'generation_result';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(GenerationResultBuilder::class)) {
            $container->set(GenerationResultBuilder::class, new GenerationResultBuilder());
        }

        if (!$container->has(GenerationResultValidator::class)) {
            $container->set(GenerationResultValidator::class, new GenerationResultValidator());
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
