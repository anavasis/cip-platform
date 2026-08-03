<?php

namespace Tests\Unit\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Modules\Acquisition\Application\CapabilityGate;
use PHPUnit\Framework\TestCase;

class CapabilityGateTest extends TestCase
{
    public function test_missing_feature_flag_backend_fails_closed(): void
    {
        $gate = new CapabilityGate;

        $this->assertFalse($gate->isEnabled(CapabilityGate::ACQUISITION));
        $this->assertFalse($gate->isEnabled(CapabilityGate::SOURCE_REGISTRY));
    }

    public function test_tenant_context_is_forwarded_to_feature_flag_service(): void
    {
        $featureFlags = $this->createMock(FeatureFlagService::class);
        $featureFlags->expects($this->once())
            ->method('isEnabled')
            ->with('acquisition', 'org-1', 'project-1')
            ->willReturn(true);

        $gate = new CapabilityGate($featureFlags);

        $this->assertTrue($gate->isEnabledFor('acquisition', 'org-1', 'project-1'));
    }

    public function test_explicit_test_override_is_preserved_for_tenant_gate(): void
    {
        $gate = new CapabilityGate;
        $gate->setEnabled(CapabilityGate::ACQUISITION, true);

        $this->assertTrue(
            $gate->forTenant('org-1', 'project-1')->isEnabled(CapabilityGate::ACQUISITION),
        );
    }
}
