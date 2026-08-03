<?php

namespace App\Modules\Editorial\Application;

use App\Modules\Editorial\Domain\Contracts\GenerationDiagnosticsSink;

final class NullGenerationDiagnostics implements GenerationDiagnosticsSink
{
    public function recordLastGeneration(array $payload): void
    {
        // no-op for isolated unit tests
    }
}
