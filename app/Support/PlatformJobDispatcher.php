<?php

namespace App\Support;

use App\Modules\Acquisition\Infrastructure\Jobs\AcquireDueSourcesJob;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Jobs\SourceConnectivityCheckJob;
use App\Modules\Editorial\Infrastructure\Jobs\GenerateArticlePreviewJob;

/**
 * Maps platform job_type values to queueable worker jobs.
 * UI orchestration only — does not contain business logic.
 */
final class PlatformJobDispatcher
{
    public static function dispatch(string $jobType, string $platformJobId): bool
    {
        return match ($jobType) {
            'acquisition.acquire_source' => tap(true, fn () => AcquireSourceJob::dispatch($platformJobId)),
            'acquisition.source_connectivity_check' => tap(true, fn () => SourceConnectivityCheckJob::dispatch($platformJobId)),
            'acquisition.acquire_due_sources' => tap(true, fn () => AcquireDueSourcesJob::dispatch($platformJobId)),
            'editorial.generate_article_preview' => tap(true, fn () => GenerateArticlePreviewJob::dispatch($platformJobId)),
            default => false,
        };
    }
}
