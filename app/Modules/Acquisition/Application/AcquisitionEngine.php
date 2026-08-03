<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionResult;

final readonly class AcquisitionEngine
{
    public const VERSION = '1.0.0';

    public function __construct(
        private AcquisitionManager $manager,
        private AcquisitionDiagnostics $diagnostics,
    ) {}

    /** @param array<string, mixed> $request */
    public function acquire(array $request): AcquisitionResult
    {
        $result = $this->manager->acquire($request);
        $this->diagnostics->recordResult($result);

        return $result;
    }

    public function diagnostics(): AcquisitionDiagnostics
    {
        return $this->diagnostics;
    }

    public function version(): string
    {
        return self::VERSION;
    }
}
