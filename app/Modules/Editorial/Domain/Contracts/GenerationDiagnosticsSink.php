<?php

namespace App\Modules\Editorial\Domain\Contracts;

/**
 * Narrow sink for orchestrator generation outcomes (no bodies/secrets).
 */
interface GenerationDiagnosticsSink
{
    /**
     * @param array<string, mixed> $payload
     */
    public function recordLastGeneration(array $payload): void;
}
