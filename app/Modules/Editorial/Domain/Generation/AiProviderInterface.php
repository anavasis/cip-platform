<?php

namespace App\Modules\Editorial\Domain\Generation;

use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;

/**
 * Provider port for generation. Slice A ships StubAiProvider only.
 * Contract is provider-neutral — adapters may wrap any offline/online provider.
 */
interface AiProviderInterface
{
    /**
     * @return array{
     *   ok: bool,
     *   provider_code: string,
     *   execution_id: string,
     *   duration_ms: int,
     *   content_text?: string,
     *   artifact_id?: string,
     *   artifact_kind?: string,
     *   content_hash?: string,
     *   mime_type?: string,
     *   error_code?: string,
     *   error_message?: string
     * }
     */
    public function generate(GenerationRequest $request): array;
}
