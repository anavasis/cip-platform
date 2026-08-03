<?php

namespace App\Modules\Announcement\Domain\Contracts;

interface CapabilityGateInterface
{
    public const ACQUISITION = 'acquisition';

    public const SOURCE_REGISTRY = 'source_registry';

    public function isEnabled(string $capability): bool;
}
