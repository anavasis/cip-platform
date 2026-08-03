<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator;
use Tests\TestCase;

class CapabilityFailClosedTest extends TestCase
{
    public function test_production_acquisition_is_rejected_when_flags_are_absent(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Fail Closed');

        $result = app(ProductionAcquisitionOrchestrator::class)->run(
            $organization->id,
            $project->id,
            ['missing-source'],
        );

        $this->assertFalse($result->success());
        $this->assertSame('capability_disabled', $result->errorCode());
    }

    public function test_project_flags_do_not_enable_acquisition_for_another_project(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $enabledProject = $this->createProject($organization->id, $owner->id, 'Enabled');
        $disabledProject = $this->createProject($organization->id, $owner->id, 'Disabled');
        $flags = app(FeatureFlagService::class);

        foreach ([CapabilityGate::ACQUISITION, CapabilityGate::SOURCE_REGISTRY] as $key) {
            $flags->upsert(
                $key,
                true,
                FeatureFlagScope::Project,
                null,
                $organization->id,
                $enabledProject->id,
            );
        }

        $gate = app(CapabilityGate::class);

        $this->assertTrue(
            $gate->isEnabledFor(CapabilityGate::ACQUISITION, $organization->id, $enabledProject->id),
        );
        $this->assertFalse(
            $gate->isEnabledFor(CapabilityGate::ACQUISITION, $organization->id, $disabledProject->id),
        );
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }
}
