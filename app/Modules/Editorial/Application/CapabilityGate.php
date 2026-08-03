<?php

namespace App\Modules\Editorial\Application;

use App\Application\Services\FeatureFlagService;

/**
 * Fail-closed Editorial capability gate backed by Kernel FeatureFlagService.
 */
final class CapabilityGate
{
    public const EDITORIAL = 'editorial';

    public const EDITORIAL_GENERATION = 'editorial_generation';

    /** @var array<string, bool> */
    private array $overrides = [];

    public function __construct(
        private readonly ?FeatureFlagService $featureFlags = null,
        private readonly ?string $organizationId = null,
        private readonly ?string $projectId = null,
    ) {}

    public function forTenant(string $organizationId, string $projectId): self
    {
        $gate = new self($this->featureFlags, trim($organizationId), trim($projectId));
        $gate->overrides = $this->overrides;

        return $gate;
    }

    public function isEnabled(string $key): bool
    {
        if (array_key_exists($key, $this->overrides)) {
            return $this->overrides[$key];
        }

        if ($this->featureFlags === null) {
            return false;
        }

        $organizationId = $this->organizationId !== null && $this->organizationId !== ''
            ? $this->organizationId
            : null;
        $projectId = $this->projectId !== null && $this->projectId !== ''
            ? $this->projectId
            : null;

        return $this->featureFlags->isEnabled($key, $organizationId, $projectId);
    }

    public function isEnabledFor(string $key, string $organizationId, string $projectId): bool
    {
        return $this->forTenant($organizationId, $projectId)->isEnabled($key);
    }

    public function generationAllowed(string $organizationId, string $projectId): bool
    {
        $tenant = $this->forTenant($organizationId, $projectId);

        return $tenant->isEnabled(self::EDITORIAL) && $tenant->isEnabled(self::EDITORIAL_GENERATION);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->overrides[$key] = $enabled;
    }
}
