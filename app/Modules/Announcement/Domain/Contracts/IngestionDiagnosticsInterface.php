<?php

namespace App\Modules\Announcement\Domain\Contracts;

interface IngestionDiagnosticsInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): void;
}
