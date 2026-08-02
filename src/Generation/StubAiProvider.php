<?php

namespace StudyMentor\ContentEngine\Generation;

use StudyMentor\ContentEngine\GenerationRequest\GenerationRequest;
use StudyMentor\ContentEngine\GenerationResult\GeneratedArtifactReference;

defined('ABSPATH') || exit;

/**
 * Deterministic offline stub provider for Editorial Slice A.
 * No HTTP, SDKs, or vendor integrations.
 */
final class StubAiProvider implements AiProviderInterface
{
    public const PROVIDER_CODE = 'stub.deterministic';

    /**
     * @param GenerationRequest $request
     * @return array<string, mixed>
     */
    public function generate(GenerationRequest $request)
    {
        $titleSeed = $request->packageId() . '|' . $request->requestHash();
        $titleHash = substr(hash('sha256', $titleSeed), 0, 8);
        $contentText = "Stub article preview\n"
            . "Announcement #" . (int) $request->announcementId() . "\n"
            . "Package: " . $request->packageId() . "\n"
            . "Request: " . $request->requestId() . "\n"
            . "Model: " . $request->modelReference()->modelId() . "\n"
            . "Token: " . $titleHash . "\n"
            . "This placeholder was produced by StubAiProvider without network access.";

        $contentHash = hash('sha256', $contentText);
        $artifactId = 'stub_art_' . (int) $request->announcementId() . '_' . substr($contentHash, 0, 12);

        return array(
            'ok' => true,
            'provider_code' => self::PROVIDER_CODE,
            'execution_id' => 'stub_exec_' . substr($request->requestHash(), 0, 12),
            'duration_ms' => 1,
            'content_text' => $contentText,
            'artifact_id' => $artifactId,
            'artifact_kind' => GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
            'content_hash' => $contentHash,
            'mime_type' => 'text/plain',
        );
    }
}
