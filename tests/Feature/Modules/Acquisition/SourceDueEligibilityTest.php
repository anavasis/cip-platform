<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Domain\Sources\SourceDueEligibility;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireDueSourcesJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\TestCase;

class SourceDueEligibilityTest extends TestCase
{
    public function test_never_attempted_source_is_due(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $source = $this->createSource($organization->id, $project->id, [
            'slug' => 'never',
            'last_acquired_at' => null,
            'last_checked_at' => null,
        ]);

        $this->assertTrue(SourceDueEligibility::isDue($source));
        $dueIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $project->id,
        ), 'id');
        $this->assertContains($source->id, $dueIds);
    }

    public function test_recently_successful_source_is_not_due(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $source = $this->createSource($organization->id, $project->id, [
            'slug' => 'recent',
            'acquire_interval_seconds' => 3600,
            'last_acquired_at' => Carbon::now()->subMinutes(5),
            'last_checked_at' => Carbon::now()->subMinutes(5),
        ]);

        $this->assertFalse(SourceDueEligibility::isDue($source));
        $dueIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $project->id,
        ), 'id');
        $this->assertNotContains($source->id, $dueIds);
    }

    public function test_interval_elapsed_source_is_due(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $source = $this->createSource($organization->id, $project->id, [
            'slug' => 'elapsed',
            'acquire_interval_seconds' => 60,
            'last_acquired_at' => Carbon::now()->subMinutes(5),
            'last_checked_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->assertTrue(SourceDueEligibility::isDue($source));
        $dueIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $project->id,
        ), 'id');
        $this->assertContains($source->id, $dueIds);
    }

    public function test_disabled_and_manual_sources_are_not_due(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $disabled = $this->createSource($organization->id, $project->id, [
            'slug' => 'disabled',
            'enabled' => false,
            'manual_only' => false,
        ]);
        $manual = $this->createSource($organization->id, $project->id, [
            'slug' => 'manual',
            'enabled' => true,
            'manual_only' => true,
        ]);

        $dueIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $project->id,
        ), 'id');
        $this->assertNotContains($disabled->id, $dueIds);
        $this->assertNotContains($manual->id, $dueIds);
    }

    public function test_project_and_organization_scope_are_enforced(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $projectA = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Due A',
            'slug' => 'due-a',
            'created_by' => $owner->id,
        ]);
        $projectB = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Due B',
            'slug' => 'due-b',
            'created_by' => $owner->id,
        ]);
        ['organization' => $otherOrg] = $this->createUserWithOrg();
        $otherProject = Project::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Due Other',
            'slug' => 'due-other',
            'created_by' => $owner->id,
        ]);
        $inA = $this->createSource($organization->id, $projectA->id, ['slug' => 'in-a']);
        $inB = $this->createSource($organization->id, $projectB->id, ['slug' => 'in-b']);
        $inOther = $this->createSource($otherOrg->id, $otherProject->id, ['slug' => 'in-other']);

        $dueIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $projectA->id,
        ), 'id');

        $this->assertContains($inA->id, $dueIds);
        $this->assertNotContains($inB->id, $dueIds);
        $this->assertNotContains($inOther->id, $dueIds);
    }

    public function test_find_due_result_is_bounded(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();

        for ($i = 0; $i < 3; $i++) {
            $this->createSource($organization->id, $project->id, ['slug' => 'bound-'.$i]);
        }

        $due = app(SourceRepositoryInterface::class)->findDue($organization->id, $project->id);
        $this->assertLessThanOrEqual(SourceDueEligibility::DEFAULT_LIMIT, count($due));
        $this->assertSame(3, count($due));
    }

    public function test_live_due_job_and_repository_share_canonical_eligibility(): void
    {
        ['organization' => $organization, 'project' => $project] = $this->seedTenant();
        $due = $this->createSource($organization->id, $project->id, [
            'slug' => 'job-due',
            'last_acquired_at' => null,
            'last_checked_at' => null,
        ]);
        $notDue = $this->createSource($organization->id, $project->id, [
            'slug' => 'job-not-due',
            'acquire_interval_seconds' => 3600,
            'last_acquired_at' => Carbon::now()->subMinutes(1),
            'last_checked_at' => Carbon::now()->subMinutes(1),
        ]);

        $repositoryIds = array_column(app(SourceRepositoryInterface::class)->findDue(
            $organization->id,
            $project->id,
        ), 'id');
        $queryIds = Source::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->tap(static fn ($query) => SourceDueEligibility::constrainEligible($query))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertContains($due->id, $repositoryIds);
        $this->assertNotContains($notDue->id, $repositoryIds);
        $this->assertSame($repositoryIds, $queryIds);

        $jobSource = (string) file_get_contents(base_path(
            'app/Modules/Acquisition/Infrastructure/Jobs/AcquireDueSourcesJob.php',
        ));
        $repositorySource = (string) file_get_contents(base_path(
            'app/Modules/Acquisition/Infrastructure/Persistence/Repositories/EloquentSourceRepository.php',
        ));
        $this->assertStringContainsString('SourceDueEligibility::constrainEligible', $jobSource);
        $this->assertStringContainsString('SourceDueEligibility::constrainEligible', $repositorySource);
        $this->assertStringNotContainsString('constrainToDue', $jobSource);

        $reflection = new ReflectionClass(AcquireDueSourcesJob::class);
        $this->assertFalse($reflection->hasMethod('constrainToDue'));
    }

    /** @return array{organization: \App\Infrastructure\Persistence\Models\Organization, project: Project} */
    private function seedTenant(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Due Eligibility',
            'slug' => 'due-eligibility-'.uniqid(),
            'created_by' => $owner->id,
        ]);

        return compact('organization', 'project');
    }

    /** @param array<string, mixed> $overrides */
    private function createSource(string $organizationId, string $projectId, array $overrides = []): Source
    {
        $slug = (string) ($overrides['slug'] ?? 'due-source');
        $feedUrl = 'http://93.184.216.34/'.$slug.'.xml';

        return Source::create(array_merge([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'http://93.184.216.34',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['93.184.216.34'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ], $overrides));
    }
}
