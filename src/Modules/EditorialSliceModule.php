<?php

namespace StudyMentor\ContentEngine\Modules;

use StudyMentor\ContentEngine\Article\ArticlePreviewRepositoryInterface;
use StudyMentor\ContentEngine\Article\InMemoryArticlePreviewRepository;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintBuilder;
use StudyMentor\ContentEngine\Blueprint\ContentBlueprintValidator;
use StudyMentor\ContentEngine\Contracts\ModuleInterface;
use StudyMentor\ContentEngine\Core\ServiceContainer;
use StudyMentor\ContentEngine\Editorial\AnnouncementSnapshotMapper;
use StudyMentor\ContentEngine\Generation\AiProviderInterface;
use StudyMentor\ContentEngine\Generation\GenerationOrchestrator;
use StudyMentor\ContentEngine\Generation\StubAiProvider;
use StudyMentor\ContentEngine\GenerationRequest\GenerationRequestBuilder;
use StudyMentor\ContentEngine\GenerationResult\GenerationResultBuilder;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\PromptContext\PromptContextBuilder;
use StudyMentor\ContentEngine\PromptPackage\PromptPackageBuilder;

defined('ABSPATH') || exit;

/**
 * Editorial Slice A wiring: snapshot mapper, stub provider, orchestrator, preview store.
 * Reuses existing BUILD-001…005 builders. No publishing or real AI providers.
 */
final class EditorialSliceModule implements ModuleInterface
{
    /**
     * @return string
     */
    public function id()
    {
        return 'editorial_slice';
    }

    /**
     * @return void
     */
    public function register(ServiceContainer $container)
    {
        if (!$container->has(AnnouncementSnapshotMapper::class)) {
            $container->set(AnnouncementSnapshotMapper::class, new AnnouncementSnapshotMapper());
        }

        if (!$container->has(AiProviderInterface::class)) {
            $container->set(AiProviderInterface::class, new StubAiProvider());
        }

        if (!$container->has(StubAiProvider::class)) {
            $container->factory(
                StubAiProvider::class,
                static function (ServiceContainer $c) {
                    $provider = $c->get(AiProviderInterface::class);

                    return $provider instanceof StubAiProvider
                        ? $provider
                        : new StubAiProvider();
                }
            );
        }

        if (!$container->has(ArticlePreviewRepositoryInterface::class)) {
            $container->set(
                ArticlePreviewRepositoryInterface::class,
                new InMemoryArticlePreviewRepository()
            );
        }

        if (!$container->has(GenerationOrchestrator::class)) {
            $container->factory(
                GenerationOrchestrator::class,
                static function (ServiceContainer $c) {
                    return new GenerationOrchestrator(
                        $c->get(AnnouncementSnapshotMapper::class),
                        $c->get(ContentBlueprintBuilder::class),
                        $c->get(ContentBlueprintValidator::class),
                        $c->get(PromptContextBuilder::class),
                        $c->get(PromptPackageBuilder::class),
                        $c->get(GenerationRequestBuilder::class),
                        $c->get(AiProviderInterface::class),
                        $c->get(GenerationResultBuilder::class),
                        $c->get(ArticlePreviewRepositoryInterface::class),
                        $c->get(PlatformDiagnostics::class)
                    );
                }
            );
        }
    }

    /**
     * @return void
     */
    public function boot(ServiceContainer $container)
    {
    }
}
