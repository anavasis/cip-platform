<?php

namespace App\Providers;

use App\Application\Services\FeatureFlagService;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\EditorialDiagnostics;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Application\GenerationOrchestrator;
use App\Modules\Editorial\Application\NullGenerationDiagnostics;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintRepositoryInterface;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintValidator;
use App\Modules\Editorial\Domain\Contracts\GenerationDiagnosticsSink;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestValidator;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultValidator;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextRepositoryInterface;
use App\Modules\Editorial\Domain\PromptContext\PromptContextValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageRepositoryInterface;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageValidator;
use App\Modules\Editorial\Application\AnnouncementSnapshotMapper;
use App\Modules\Editorial\Infrastructure\Generation\StubAiProvider;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentArticlePreviewRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentContentBlueprintRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationRequestRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationResultRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentPromptContextRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentPromptPackageRepository;
use Illuminate\Support\ServiceProvider;

class EditorialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CapabilityGate::class, function ($app) {
            return new CapabilityGate($app->make(FeatureFlagService::class));
        });
        $this->app->singleton(EditorialDiagnostics::class);
        $this->app->bind(GenerationDiagnosticsSink::class, EditorialDiagnostics::class);

        $this->app->bind(ContentBlueprintRepositoryInterface::class, EloquentContentBlueprintRepository::class);
        $this->app->bind(PromptContextRepositoryInterface::class, EloquentPromptContextRepository::class);
        $this->app->bind(PromptPackageRepositoryInterface::class, EloquentPromptPackageRepository::class);
        $this->app->bind(GenerationRequestRepositoryInterface::class, EloquentGenerationRequestRepository::class);
        $this->app->bind(GenerationResultRepositoryInterface::class, EloquentGenerationResultRepository::class);
        $this->app->bind(ArticlePreviewRepositoryInterface::class, EloquentArticlePreviewRepository::class);

        $this->app->bind(AiProviderInterface::class, StubAiProvider::class);

        $this->app->singleton(GenerationOrchestrator::class, function ($app) {
            return new GenerationOrchestrator(
                $app->make(AnnouncementSnapshotMapper::class),
                $app->make(ContentBlueprintBuilder::class),
                $app->make(ContentBlueprintValidator::class),
                $app->make(PromptContextBuilder::class),
                $app->make(PromptContextValidator::class),
                $app->make(PromptPackageBuilder::class),
                $app->make(PromptPackageValidator::class),
                $app->make(GenerationRequestBuilder::class),
                $app->make(GenerationRequestValidator::class),
                $app->make(AiProviderInterface::class),
                $app->make(GenerationResultBuilder::class),
                $app->make(GenerationResultValidator::class),
                $app->make(ArticlePreviewRepositoryInterface::class),
                $app->make(GenerationDiagnosticsSink::class),
            );
        });
    }
}
