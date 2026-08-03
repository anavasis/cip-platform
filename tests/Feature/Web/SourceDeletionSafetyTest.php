<?php

namespace Tests\Feature\Web;

use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRunItem;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Support\OperatorContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SourceDeletionSafetyTest extends TestCase
{
    public function test_unused_source_can_be_deleted(): void
    {
        $ctx = $this->operatorContext();
        $source = $this->createSource($ctx, 'unused-src');

        $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->delete(route('app.sources.destroy', $source))->assertRedirect(route('app.sources.index'));

        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    public function test_source_with_announcement_cannot_be_deleted(): void
    {
        $ctx = $this->operatorContext();
        $source = $this->createSource($ctx, 'ann-src');
        $announcement = $this->createAnnouncement($ctx, $source);

        $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->from(route('app.sources.index'))
            ->delete(route('app.sources.destroy', $source))
            ->assertRedirect(route('app.sources.index'))
            ->assertSessionHasErrors('form');

        $this->assertDatabaseHas('sources', ['id' => $source->id]);
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_source_with_acquisition_history_cannot_be_deleted(): void
    {
        $ctx = $this->operatorContext();
        $source = $this->createSource($ctx, 'acq-src');
        $run = AcquisitionRun::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'run_id' => 'run_'.Str::lower(Str::random(10)),
            'status' => 'completed',
            'sources_requested' => 1,
            'sources_succeeded' => 1,
            'sources_failed' => 0,
        ]);
        AcquisitionRunItem::create([
            'acquisition_run_id' => $run->id,
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'source_id' => $source->id,
            'success' => true,
            'error_code' => null,
            'result_meta' => ['ok' => true],
        ]);

        $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->from(route('app.sources.index'))
            ->delete(route('app.sources.destroy', $source))
            ->assertRedirect(route('app.sources.index'))
            ->assertSessionHasErrors('form');

        $this->assertDatabaseHas('sources', ['id' => $source->id]);
        $this->assertDatabaseHas('acquisition_run_items', ['source_id' => $source->id]);
    }

    public function test_cross_project_dependencies_do_not_block_or_leak(): void
    {
        $ctxA = $this->operatorContext('Org A', 'Project A');
        $ctxB = $this->operatorContext('Org B', 'Project B');
        $sourceA = $this->createSource($ctxA, 'shared-slug-a');
        $sourceB = $this->createSource($ctxB, 'shared-slug-b');
        $this->createAnnouncement($ctxB, $sourceB);

        $this->actingAs($ctxA['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctxA['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctxA['project']->id,
        ])->delete(route('app.sources.destroy', $sourceA))->assertRedirect(route('app.sources.index'));

        $this->assertDatabaseMissing('sources', ['id' => $sourceA->id]);
        $this->assertDatabaseHas('sources', ['id' => $sourceB->id]);
    }

    public function test_unauthorized_project_cannot_delete(): void
    {
        $ctxA = $this->operatorContext('Org Own', 'Project Own');
        $ctxB = $this->operatorContext('Org Other', 'Project Other');
        $sourceB = $this->createSource($ctxB, 'foreign-src');

        $this->actingAs($ctxA['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctxA['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctxA['project']->id,
        ])->delete(route('app.sources.destroy', $sourceB))->assertNotFound();

        $this->assertDatabaseHas('sources', ['id' => $sourceB->id]);
    }

    public function test_disabling_remains_available(): void
    {
        $ctx = $this->operatorContext();
        $source = $this->createSource($ctx, 'disable-src');

        $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->post(route('app.sources.disable', $source))->assertRedirect();

        $this->assertFalse((bool) $source->fresh()->enabled);
    }

    public function test_delete_form_is_csrf_protected_and_uses_delete_method(): void
    {
        $ctx = $this->operatorContext();
        $this->createSource($ctx, 'form-src');

        $html = $this->actingAs($ctx['user'])->withSession([
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ])->get(route('app.sources.index'))->assertOk()->getContent();

        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('Disable is the normal safe action', $html);
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function operatorContext(string $orgName = 'Ops Org', string $projectName = 'Ops Project'): array
    {
        $user = User::factory()->create([
            'password' => Hash::make('password-secret'),
        ]);
        $organization = app(OrganizationService::class)->create($user, $orgName);
        $project = app(ProjectService::class)->create($organization, $user, $projectName);

        return compact('user', 'organization', 'project');
    }

    /**
     * @param  array{organization: mixed, project: mixed}  $ctx
     */
    private function createSource(array $ctx, string $slug): Source
    {
        $result = app(SourceRegistryService::class)->create($ctx['organization']->id, $ctx['project']->id, [
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/'.$slug.'-'.uniqid().'.xml',
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);
        $this->assertTrue($result['success'] ?? false);

        return Source::query()->whereKey($result['id'])->firstOrFail();
    }

    /**
     * @param  array{organization: mixed, project: mixed}  $ctx
     */
    private function createAnnouncement(array $ctx, Source $source): Announcement
    {
        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/a-'.uniqid(),
            'raw_title' => 'Protected Announcement',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Protected Announcement'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
