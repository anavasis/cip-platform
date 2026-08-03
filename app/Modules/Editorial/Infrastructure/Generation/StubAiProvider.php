<?php

namespace App\Modules\Editorial\Infrastructure\Generation;

use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationResult\GeneratedArtifactReference;

/**
 * Deterministic offline stub provider for Editorial Slice A.
 * No HTTP, SDKs, or vendor integrations.
 */
final class StubAiProvider implements AiProviderInterface
{
    public const PROVIDER_CODE = 'stub.deterministic';

    public function generate(GenerationRequest $request): array
    {
        $titleSeed = $request->packageId().'|'.$request->requestHash();
        $titleHash = substr(hash('sha256', $titleSeed), 0, 8);
        $contentText = "Stub article preview\n"
            .'Announcement '.$request->announcementId()."\n"
            .'Package: '.$request->packageId()."\n"
            .'Request: '.$request->requestId()."\n"
            .'Model: '.$request->modelReference()->modelId()."\n"
            .'Token: '.$titleHash."\n"
            .'This placeholder was produced by StubAiProvider without network access.';

        $contentHash = hash('sha256', $contentText);
        $artifactId = 'stub_art_'.str_replace('-', '', $request->announcementId()).'_'.substr($contentHash, 0, 12);

        return [
            'ok' => true,
            'provider_code' => self::PROVIDER_CODE,
            'execution_id' => 'stub_exec_'.substr($request->requestHash(), 0, 12),
            'duration_ms' => 1,
            'content_text' => $contentText,
            'artifact_id' => $artifactId,
            'artifact_kind' => GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
            'content_hash' => $contentHash,
            'mime_type' => 'text/plain',
        ];
    }
}
