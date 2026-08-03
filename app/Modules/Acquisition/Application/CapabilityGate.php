<?php

namespace App\Modules\Acquisition\Application;

use App\Application\Services\FeatureFlagService;
use App\Modules\Acquisition\Domain\Contracts\CapabilityGateInterface;

final class CapabilityGate implements CapabilityGateInterface
{
    public const ACQUISITION = 'acquisition';

    public const SOURCE_REGISTRY = 'source_registry';

    /** @var array<string, bool> */
    private array $overrides = [];

    public function __construct(
        private readonly ?FeatureFlagService $featureFlags = null,
        private readonly ?string $organizationId = null,
        private readonly ?string $projectId = null,
    ) {}

    public function isEnabled(string $key): bool
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        if ($this->featureFlags !== null) {
            return $this->featureFlags->isEnabled($key, $this->organizationId, $this->projectId);
        }

        return in_array($key, [self::ACQUISITION, self::SOURCE_REGISTRY], true);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->overrides[$key] = $enabled;
    }
}
