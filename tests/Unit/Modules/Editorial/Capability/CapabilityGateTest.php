<?php

namespace Tests\Unit\Modules\Editorial\Capability;

use App\Application\Services\FeatureFlagService;
use App\Modules\Editorial\Application\CapabilityGate;
use Mockery;
use Tests\TestCase;

class CapabilityGateTest extends TestCase
{
    public function test_fail_closed_when_flags_missing(): void
    {
        $gate = new CapabilityGate;
        $this->assertFalse($gate->generationAllowed('o', 'p'));
        $this->assertFalse($gate->isEnabled(CapabilityGate::EDITORIAL));
    }

    public function test_project_scoped_flags_do_not_leak(): void
    {
        $flags = Mockery::mock(FeatureFlagService::class);
        $flags->shouldReceive('isEnabled')
            ->with(CapabilityGate::EDITORIAL, 'org-a', 'proj-a')
            ->andReturn(true);
        $flags->shouldReceive('isEnabled')
            ->with(CapabilityGate::EDITORIAL_GENERATION, 'org-a', 'proj-a')
            ->andReturn(true);
        $flags->shouldReceive('isEnabled')
            ->with(CapabilityGate::EDITORIAL, 'org-a', 'proj-b')
            ->andReturn(false);
        $flags->shouldReceive('isEnabled')
            ->with(CapabilityGate::EDITORIAL_GENERATION, 'org-a', 'proj-b')
            ->andReturn(false);

        $gate = new CapabilityGate($flags);
        $this->assertTrue($gate->generationAllowed('org-a', 'proj-a'));
        $this->assertFalse($gate->generationAllowed('org-a', 'proj-b'));
    }
}
