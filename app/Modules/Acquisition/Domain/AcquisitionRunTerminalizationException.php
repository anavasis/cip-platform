<?php

namespace App\Modules\Acquisition\Domain;

use RuntimeException;

final class AcquisitionRunTerminalizationException extends RuntimeException
{
    public static function persistenceFailed(string $runId): self
    {
        return new self('run_terminalization_failed:'.$runId);
    }
}
