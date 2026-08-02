<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageValidator;

defined('ABSPATH') || exit;

/**
 * Registers Prompt Package domain services (BUILD-003).
 * No UI, persistence, prompt text/rendering, providers, or Generation Request.
 */
final class PromptPackageModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'prompt_package';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(PromptPackageBuilder::class)) {
            $container->set(PromptPackageBuilder::class, new PromptPackageBuilder());
        }

        if (!$container->has(PromptPackageValidator::class)) {
            $container->set(PromptPackageValidator::class, new PromptPackageValidator());
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
