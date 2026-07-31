<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptContext\PromptContextValidator;

defined('ABSPATH') || exit;

/**
 * Registers Prompt Context domain services (BUILD-002).
 * No UI, persistence adapter, prompt templates, providers, or packages.
 */
final class PromptContextModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'prompt_context';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(PromptContextBuilder::class)) {
            $container->set(PromptContextBuilder::class, new PromptContextBuilder());
        }

        if (!$container->has(PromptContextValidator::class)) {
            $container->set(PromptContextValidator::class, new PromptContextValidator());
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
