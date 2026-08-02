<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestBuilder;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestValidator;

defined('ABSPATH') || exit;

/**
 * Registers Generation Request domain services (BUILD-004).
 * No providers, HTTP, queues, workers, persistence, UI, or Generation Result.
 */
final class GenerationRequestModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'generation_request';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(GenerationRequestBuilder::class)) {
            $container->set(GenerationRequestBuilder::class, new GenerationRequestBuilder());
        }

        if (!$container->has(GenerationRequestValidator::class)) {
            $container->set(GenerationRequestValidator::class, new GenerationRequestValidator());
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
