<?php

namespace Tests\Unit\Modules\Intelligence;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentIntelligencePlannerTest extends TestCase
{
    private ContentIntelligencePlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = app(ContentIntelligencePlanner::class);
    }

    public function test_missing_profile_returns_unresolved_no_publish(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, 'Missing Profile Org');
        $project = app(ProjectService::class)->create($organization, $user, 'Missing Profile Project');
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $organization->id, $project->id);

        $plan = $this->planner->planForAnnouncement($organization->id, $project->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_UNRESOLVED, $plan->status());
        $this->assertSame(ContentIntelligencePlan::ACTION_NO_PUBLISH, $plan->action());
        $this->assertContains('content_intelligence_profile_missing', $plan->warnings());
        $this->assertSame([], $plan->publishingOperations());
    }

    public function test_greek_k_matches_configured_entity(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('Νέα για ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame('asep-6k-2026', $plan->entityId());
    }

    public function test_latin_k_matches_same_entity_when_regex_supports_it(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6K/2026 update', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame('asep-6k-2026', $plan->entityId());
    }

    public function test_different_urls_same_title_resolve_same_entity(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcementA = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id, 'https://example.com/a');
        $announcementB = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id, 'https://example.com/b');

        $planA = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcementA);
        $planB = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcementB);

        $this->assertSame($planA->entityId(), $planB->entityId());
        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $planA->status());
        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $planB->status());
    }

    public function test_six_k_and_seven_k_remain_different_entities(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][] = [
            'entity_id' => 'asep-7k-2026',
            'label' => 'ΑΣΕΠ 7Κ/2026',
            'patterns' => ['7\\s*[ΚK]\\s*\\/\\s*2026'],
            'match_scope' => 'title',
            'content_role' => 'satellite',
            'canonical_target_url' => 'https://example.test/asep-7k-2026',
            'seo' => ['slug' => 'asep-7k-2026'],
        ];
        $ctx = $this->seedProfile($profile);

        $plan6 = $this->planner->planForAnnouncement(
            $ctx['organization']->id,
            $ctx['project']->id,
            $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id),
        );
        $plan7 = $this->planner->planForAnnouncement(
            $ctx['organization']->id,
            $ctx['project']->id,
            $this->makeAnnouncement('ΑΣΕΠ 7Κ/2026', $ctx['organization']->id, $ctx['project']->id),
        );

        $this->assertSame('asep-6k-2026', $plan6->entityId());
        $this->assertSame('asep-7k-2026', $plan7->entityId());
    }

    public function test_canonical_target_yields_update_existing(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::ACTION_UPDATE_EXISTING, $plan->action());
    }

    public function test_missing_canonical_target_yields_create_new(): void
    {
        $profile = $this->satelliteProfile();
        unset($profile['entity_rules'][0]['canonical_target_url']);
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::ACTION_CREATE_NEW, $plan->action());
    }

    public function test_publish_false_yields_no_publish(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['publish'] = false;
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::ACTION_NO_PUBLISH, $plan->action());
        $this->assertSame([], $plan->publishingOperations());
    }

    public function test_satellite_parent_hub_yields_update_required(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame('satellite', $plan->contentRole());
        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_UPDATE_REQUIRED, $plan->hubImpact());
    }

    public function test_deterministic_seo_fields(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026 announcement', $ctx['organization']->id, $ctx['project']->id);

        $planA = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);
        $planB = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame($planA->seoPlan(), $planB->seoPlan());
        $this->assertSame('asep-6k-2026', $planA->seoPlan()['slug']);
        $this->assertSame('ΑΣΕΠ 6Κ/2026: ΑΣΕΠ 6Κ/2026 announcement', $planA->seoPlan()['seo_title']);
    }

    public function test_template_placeholder_replacement(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026 Custom title', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);
        $seo = $plan->seoPlan();

        $this->assertSame('ΑΣΕΠ 6Κ/2026: ΑΣΕΠ 6Κ/2026 Custom title', $seo['seo_title']);
        $this->assertSame('ΑΣΕΠ 6Κ/2026 Custom title', $seo['h1']);
    }

    public function test_unsupported_template_placeholder_fails_closed_for_field(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['seo']['seo_title_template'] = 'Bad {unsupported} title';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertNull($plan->seoPlan()['seo_title']);
        $this->assertContains('unsupported_template_placeholder_in_seo_title_template: {unsupported}', $plan->warnings());
        $this->assertContains('seo_title_missing', $plan->warnings());
    }

    public function test_multiple_entity_matches_are_ambiguous_no_publish(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][] = [
            'entity_id' => 'asep-generic-2026',
            'label' => 'ΑΣΕΠ generic',
            'patterns' => ['ΑΣΕΠ'],
            'match_scope' => 'title',
            'content_role' => 'satellite',
            'canonical_target_url' => 'https://example.test/generic',
            'seo' => ['slug' => 'generic'],
        ];
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_AMBIGUOUS, $plan->status());
        $this->assertSame(ContentIntelligencePlan::ACTION_NO_PUBLISH, $plan->action());
        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_NONE, $plan->hubImpact());
        $this->assertSame([], $plan->publishingOperations());
    }

    public function test_invalid_regex_fails_closed(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['patterns'] = ['(?P<unclosed'];
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_INVALID_PROFILE, $plan->status());
        $this->assertContains('invalid_regex_pattern', $plan->warnings());
    }

    public function test_title_only_scope_does_not_inspect_body(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('Unrelated title', $ctx['organization']->id, $ctx['project']->id, 'https://example.com/x', [
            'content' => 'Contains ΑΣΕΠ 6Κ/2026 in body only',
        ]);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_UNRESOLVED, $plan->status());
    }

    public function test_title_and_body_scope_may_inspect_retained_content(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('Unrelated title', $ctx['organization']->id, $ctx['project']->id, 'https://example.com/x', [
            'description' => 'Details for ΑΣΕΠ 6Κ/2026 applicants',
        ]);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame('asep-6k-2026', $plan->entityId());
        $this->assertSame(ContentIntelligencePlan::MATCH_LOCATION_BODY, $plan->matchLocation());
        $this->assertFalse($plan->primaryBindingEligible());
        $this->assertContains('body_only_match_reference_only', $plan->warnings());
        $this->assertArrayHasKey('match_location', $plan->toArray());
        $this->assertArrayHasKey('primary_binding_eligible', $plan->toArray());
    }

    public function test_genuine_satellite_title_match_is_primary_eligible(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'ΑΣΕΠ 6Κ/2026: Ανοιχτές αιτήσεις για 315 μόνιμες θέσεις ΠΕ και ΤΕ',
            $ctx['organization']->id,
            $ctx['project']->id,
            'https://example.com/genuine-6k',
            ['description' => 'Supporting body text'],
        );

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame('asep-6k-2026', $plan->entityId());
        $this->assertSame(ContentIntelligencePlan::MATCH_LOCATION_TITLE, $plan->matchLocation());
        $this->assertTrue($plan->primaryBindingEligible());
        $this->assertNotContains('body_only_match_reference_only', $plan->warnings());
    }

    public function test_hub_overview_body_mention_does_not_resolve_satellite_when_title_matches_hub(): void
    {
        $profile = $this->hubAndSatelliteProfile();
        $profile['entity_rules'][1]['match_scope'] = 'title_and_body';
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'ΑΣΕΠ 2026: Πάνω από 11.000 θέσεις και διορισμοί – Προκηρύξεις και υποστήριξη αιτήσεων',
            $ctx['organization']->id,
            $ctx['project']->id,
            'https://example.com/hub-overview',
            ['description' => 'Overview mentioning ΑΣΕΠ 6Κ/2026 and other processes.'],
        );

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame('asep-2026', $plan->entityId());
        $this->assertSame(ContentIntelligencePlan::MATCH_LOCATION_TITLE, $plan->matchLocation());
        $this->assertTrue($plan->primaryBindingEligible());
        $this->assertSame('hub', $plan->contentRole());
    }

    public function test_multiple_body_only_satellite_mentions_are_ambiguous(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $profile['entity_rules'][] = [
            'entity_id' => 'asep-7k-2026',
            'label' => 'ΑΣΕΠ 7Κ/2026',
            'patterns' => ['7\\s*[ΚK]\\s*\\/\\s*2026'],
            'match_scope' => 'title_and_body',
            'content_role' => 'satellite',
            'canonical_target_url' => 'https://example.test/asep-7k-2026',
            'seo' => ['slug' => 'asep-7k-2026'],
        ];
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement(
            'ΑΣΕΠ 2026: Hub overview',
            $ctx['organization']->id,
            $ctx['project']->id,
            'https://example.com/hub-multi-body',
            ['description' => 'Mentions ΑΣΕΠ 6Κ/2026 and ΑΣΕΠ 7Κ/2026 in the body.'],
        );

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_AMBIGUOUS, $plan->status());
        $this->assertFalse($plan->primaryBindingEligible());
    }

    public function test_allow_body_primary_match_opt_in_restores_body_primary_eligibility(): void
    {
        $profile = $this->satelliteProfile();
        $profile['entity_rules'][0]['match_scope'] = 'title_and_body';
        $profile['entity_rules'][0]['allow_body_primary_match'] = true;
        $ctx = $this->seedProfile($profile);
        $announcement = $this->makeAnnouncement('Unrelated title', $ctx['organization']->id, $ctx['project']->id, 'https://example.com/x', [
            'description' => 'Details for ΑΣΕΠ 6Κ/2026 applicants',
        ]);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $plan->status());
        $this->assertSame(ContentIntelligencePlan::MATCH_LOCATION_BODY, $plan->matchLocation());
        $this->assertTrue($plan->primaryBindingEligible());
        $this->assertNotContains('body_only_match_reference_only', $plan->warnings());
    }

    public function test_project_a_config_never_resolves_project_b_announcement(): void
    {
        $ctxA = $this->seedProfile($this->satelliteProfile(), 'Org A', 'Project A');
        $ctxB = $this->seedProfile([
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'entity_rules' => [],
        ], 'Org B', 'Project B');

        $announcementB = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctxB['organization']->id, $ctxB['project']->id);

        $planB = $this->planner->planForAnnouncement($ctxB['organization']->id, $ctxB['project']->id, $announcementB);

        $this->assertSame(ContentIntelligencePlan::STATUS_UNRESOLVED, $planB->status());

        $announcementA = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctxA['organization']->id, $ctxA['project']->id);
        $planA = $this->planner->planForAnnouncement($ctxA['organization']->id, $ctxA['project']->id, $announcementA);

        $this->assertSame(ContentIntelligencePlan::STATUS_RESOLVED, $planA->status());
        $this->assertSame('asep-6k-2026', $planA->entityId());
    }

    public function test_mismatched_project_announcement_fails_closed(): void
    {
        $ctxA = $this->seedProfile($this->satelliteProfile(), 'Mismatch Org A', 'Mismatch Project A');
        $ctxB = $this->seedProfile([
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'entity_rules' => [],
        ], 'Mismatch Org B', 'Mismatch Project B');

        $announcementB = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctxB['organization']->id, $ctxB['project']->id);

        $plan = $this->planner->planForAnnouncement(
            $ctxA['organization']->id,
            $ctxA['project']->id,
            $announcementB,
        );

        $this->assertSame(ContentIntelligencePlan::STATUS_UNRESOLVED, $plan->status());
        $this->assertSame(ContentIntelligencePlan::ACTION_NO_PUBLISH, $plan->action());
        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_NONE, $plan->hubImpact());
        $this->assertSame([], $plan->publishingOperations());
        $this->assertContains('announcement_tenant_mismatch', $plan->warnings());
    }

    public function test_mismatched_organization_announcement_fails_closed(): void
    {
        $ctxA = $this->seedProfile($this->satelliteProfile(), 'Tenant Org A', 'Tenant Project A');
        $userB = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organizationB = app(OrganizationService::class)->create($userB, 'Tenant Org B');
        $projectB = app(ProjectService::class)->create($organizationB, $userB, 'Tenant Project B');

        $announcementB = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $organizationB->id, $projectB->id);

        $plan = $this->planner->planForAnnouncement(
            $ctxA['organization']->id,
            $ctxA['project']->id,
            $announcementB,
        );

        $this->assertSame(ContentIntelligencePlan::STATUS_UNRESOLVED, $plan->status());
        $this->assertSame(ContentIntelligencePlan::ACTION_NO_PUBLISH, $plan->action());
        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_NONE, $plan->hubImpact());
        $this->assertSame([], $plan->publishingOperations());
        $this->assertContains('announcement_tenant_mismatch', $plan->warnings());
    }

    public function test_hub_update_existing_emits_single_operation(): void
    {
        $ctx = $this->seedProfile($this->hubProfile(withCanonicalTarget: true));
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 2026 hub', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_SELF_UPDATE, $plan->hubImpact());
        $this->assertCount(1, $plan->publishingOperations());
        $this->assertSame('update_existing', $plan->publishingOperations()[0]['operation']);
        $this->assertSame('asep-2026', $plan->publishingOperations()[0]['entity_id']);
        $this->assertSame('https://example.test/asep-2026-hub', $plan->publishingOperations()[0]['target_url']);
        $this->assertSame('plan_only', $plan->publishingOperations()[0]['mode']);
        $this->assertFalse($this->hasOperation($plan->publishingOperations(), 'update_hub'));
    }

    public function test_hub_create_new_emits_single_operation(): void
    {
        $ctx = $this->seedProfile($this->hubProfile(withCanonicalTarget: false));
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 2026 hub', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertSame(ContentIntelligencePlan::HUB_IMPACT_SELF_UPDATE, $plan->hubImpact());
        $this->assertCount(1, $plan->publishingOperations());
        $this->assertSame('create_new', $plan->publishingOperations()[0]['operation']);
        $this->assertSame('asep-2026', $plan->publishingOperations()[0]['entity_id']);
        $this->assertNull($plan->publishingOperations()[0]['target_url']);
        $this->assertSame('plan_only', $plan->publishingOperations()[0]['mode']);
        $this->assertFalse($this->hasOperation($plan->publishingOperations(), 'update_hub'));
    }

    public function test_satellite_still_emits_satellite_and_parent_hub_operations(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertCount(2, $plan->publishingOperations());
        $this->assertSame('update_existing', $plan->publishingOperations()[0]['operation']);
        $this->assertSame('asep-6k-2026', $plan->publishingOperations()[0]['entity_id']);
        $this->assertSame('update_hub', $plan->publishingOperations()[1]['operation']);
        $this->assertSame('asep-2026', $plan->publishingOperations()[1]['entity_id']);
        $this->assertNotSame(
            $plan->publishingOperations()[0]['entity_id'],
            $plan->publishingOperations()[1]['entity_id'],
        );
    }

    public function test_publishing_operations_always_plan_only_mode(): void
    {
        $ctx = $this->seedProfile($this->satelliteProfile());
        $announcement = $this->makeAnnouncement('ΑΣΕΠ 6Κ/2026', $ctx['organization']->id, $ctx['project']->id);

        $plan = $this->planner->planForAnnouncement($ctx['organization']->id, $ctx['project']->id, $announcement);

        $this->assertNotEmpty($plan->publishingOperations());
        foreach ($plan->publishingOperations() as $operation) {
            $this->assertSame('plan_only', $operation['mode']);
        }
    }

    public function test_validate_profile_rejects_non_plan_only_publishing_mode(): void
    {
        $profile = $this->satelliteProfile();
        $profile['publishing_mode'] = 'draft';

        $validation = $this->planner->validateProfile($profile);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('plan_only', implode(' ', $validation['errors']));
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function makeAnnouncement(
        string $title,
        string $organizationId,
        string $projectId,
        string $url = 'https://example.com/item',
        array $rawPayload = [],
    ): Announcement {
        $feedUrl = 'https://example.com/feed-'.Str::lower(Str::random(8)).'.xml';
        $source = Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => 'src-'.Str::lower(Str::random(6)),
            'name' => 'Test Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);

        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', $url),
            'identity_basis' => 'canonical_url',
            'canonical_url' => $url,
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title),
            'raw_payload' => $rawPayload !== [] ? $rawPayload : ['title' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function hubProfile(bool $withCanonicalTarget): array
    {
        $rule = [
            'entity_id' => 'asep-2026',
            'label' => 'ΑΣΕΠ 2026',
            'patterns' => ['ΑΣΕΠ\\s+2026'],
            'match_scope' => 'title',
            'content_role' => 'hub',
            'seo' => [
                'search_intent' => 'ASEP 2026 hub overview',
                'slug' => 'asep-2026',
                'seo_title_template' => '{announcement_title}',
                'h1_template' => '{announcement_title}',
                'meta_description_template' => 'ASEP 2026 hub meta.',
            ],
        ];

        if ($withCanonicalTarget) {
            $rule['canonical_target_url'] = 'https://example.test/asep-2026-hub';
        }

        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'entity_rules' => [$rule],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    private function hasOperation(array $operations, string $operationName): bool
    {
        foreach ($operations as $operation) {
            if (($operation['operation'] ?? '') === $operationName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function hubAndSatelliteProfile(): array
    {
        $hubRule = [
            'entity_id' => 'asep-2026',
            'label' => 'ΑΣΕΠ 2026',
            'patterns' => ['ΑΣΕΠ\\s+2026'],
            'match_scope' => 'title',
            'content_role' => 'hub',
            'canonical_target_url' => 'https://example.test/asep-2026-hub',
            'seo' => [
                'search_intent' => 'ASEP 2026 hub overview',
                'slug' => 'asep-2026',
                'seo_title_template' => '{announcement_title}',
                'h1_template' => '{announcement_title}',
                'meta_description_template' => 'ASEP 2026 hub meta.',
            ],
        ];

        $satelliteRule = $this->satelliteProfile()['entity_rules'][0];

        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'studymentor.gr',
            'entity_rules' => [$hubRule, $satelliteRule],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function satelliteProfile(): array
    {
        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'studymentor.gr',
            'entity_rules' => [
                [
                    'entity_id' => 'asep-6k-2026',
                    'label' => 'ΑΣΕΠ 6Κ/2026',
                    'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'canonical_target_url' => 'https://example.test/asep-6k-2026',
                    'parent_hub' => [
                        'entity_id' => 'asep-2026',
                        'label' => 'ΑΣΕΠ 2026',
                        'url' => 'https://example.test/asep-2026',
                    ],
                    'seo' => [
                        'search_intent' => 'Specific ASEP 6K/2026 information',
                        'slug' => 'asep-6k-2026',
                        'seo_title_template' => 'ΑΣΕΠ 6Κ/2026: {announcement_title}',
                        'h1_template' => '{announcement_title}',
                        'meta_description_template' => 'Ενημέρωση για την ΑΣΕΠ 6Κ/2026.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function seedProfile(array $profile, string $orgName = 'CI Org', string $projectName = 'CI Project'): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, $orgName);
        $project = app(ProjectService::class)->create($organization, $user, $projectName);

        app(ConfigurationService::class)->set(
            $organization->id,
            ContentIntelligencePlanner::PROFILE_KEY,
            ['value' => $profile],
            $project->id,
            $user,
        );

        return compact('user', 'organization', 'project');
    }
}
