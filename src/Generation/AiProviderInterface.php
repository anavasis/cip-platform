<?php

namespace StudyMentor\ContentEngine\Generation;

use StudyMentor\ContentEngine\GenerationRequest\GenerationRequest;

defined('ABSPATH') || exit;

/**
 * Provider port for generation. Slice A ships StubAiProvider only.
 */
interface AiProviderInterface
{
    /**
     * @param GenerationRequest $request
     * @return array{
     *   body: string,
     *   artifact_id: string,
     *   artifact_kind: string,
     *   content_hash: string,
     *   mime_type: string,
     *   execution_id: string,
     *   provider_code: string,
     *   duration_ms: int
     * }
     */
    public function generate(GenerationRequest $request);
}
