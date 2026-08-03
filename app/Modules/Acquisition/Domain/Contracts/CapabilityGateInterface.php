<?php

namespace App\Modules\Acquisition\Domain\Contracts;

interface CapabilityGateInterface
{
    public function isEnabled(string $key): bool;
}
