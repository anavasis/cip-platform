<?php

namespace Tests\Feature\Web;

use App\Application\Services\FeatureFlagService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Application\CapabilityGate as AcquisitionCapabilityGate;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Infrastructure\Generation\StubAiProvider;
use App\Modules\Editorial\Domain\Generation\AiProviderInterface;
use App\Support\OperatorContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    public function test_operator_can_login_select_project_generate_and_preview(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@example.com',
            'password' => Hash::make('password-secret'),
        ]);
        $organization = app(OrganizationService::class)->create($user, 'Ops Org');
        $project = app(ProjectService::class)->create($organization, $user, 'Ops Project');
        $flags = app(FeatureFlagService::class);
        foreach ([
            CapabilityGate::EDITORIAL,
            CapabilityGate::EDITORIAL_GENERATION,
            AcquisitionCapabilityGate::ACQUISITION,
            AcquisitionCapabilityGate::SOURCE_REGISTRY,
        ] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);
        }

        $this->get('/login')->assertOk();
        $this->post('/login', [
            'email' => 'ops@example.com',
            'password' => 'password-secret',
            'remember' => '1',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post(route('app.context.store'), [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
        ])->assertRedirect(route('app.dashboard'));

        $this->get(route('app.dashboard'))->assertOk()->assertSee('Operational dashboard');

        $this->get(route('app.sources.create'))->assertOk();
        $this->post(route('app.sources.store'), [
            'slug' => 'ops-feed',
            'name' => 'Ops Feed',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/ops-'.uniqid().'.xml',
            'allowed_domains_text' => 'example.com',
            'parser_profile' => '',
            'acquire_interval_seconds' => 3600,
            'enabled' => '1',
            'manual_only' => '1',
        ])->assertRedirect(route('app.sources.index'));

        $source = Source::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('slug', 'ops-feed')
            ->firstOrFail();

        $this->get(route('app.sources.index'))->assertOk()->assertSee('Ops Feed');
        $this->post(route('app.sources.check', $source))->assertRedirect();
        $this->post(route('app.sources.run', $source))->assertRedirect();

        $announcement = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/a-'.uniqid(),
            'raw_title' => 'Ops Announcement',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Ops Announcement', 'summary' => 'Summary for operators.'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->get(route('app.announcements.index'))->assertOk()->assertSee('Ops Announcement');
        $this->get(route('app.announcements.show', $announcement))->assertOk()->assertSee('Timeline');
        $this->get(route('app.editorial.show', $announcement))->assertOk();

        $this->post(route('app.editorial.generate', $announcement))
            ->assertRedirect(route('app.preview.show', $announcement));

        $this->get(route('app.preview.show', $announcement))
            ->assertOk()
            ->assertSee('Article preview')
            ->assertSee('Copy title')
            ->assertSee('Copy body')
            ->assertSee('Copy markdown')
            ->assertSee('Copy full article')
            ->assertSee('navigator.clipboard.writeText');

        $this->get(route('app.preview.download', $announcement))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get(route('app.diagnostics'))
            ->assertOk()
            ->assertSee('Health checks')
            ->assertSee('database')
            ->assertSee('redis')
            ->assertSee('storage')
            ->assertSee('scheduler')
            ->assertSee('provider');

        $this->get(route('app.queue.index'))->assertOk()->assertSee('Queue');
        $this->get(route('app.settings.edit'))->assertOk()->assertSee('AI provider');
        $this->get(route('app.acquisition.index'))->assertOk()->assertSee('Acquisition');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_setup_wizard_creates_admin_org_and_project(): void
    {
        $this->get(route('setup.show'))->assertOk()->assertSee('Initial setup wizard');

        $response = $this->post(route('setup.store'), [
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'password-secret',
            'organization_name' => 'First Org',
            'project_name' => 'First Project',
            'ai_model' => 'gpt-5',
            'ai_temperature' => 0.2,
            'ai_max_tokens' => 2048,
            'ai_timeout_seconds' => 60,
            'enable_editorial' => '1',
            'enable_acquisition' => '1',
        ]);

        $response->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('organizations', ['name' => 'First Org']);
        $this->assertDatabaseHas('projects', ['name' => 'First Project']);

        $this->get(route('setup.show'))->assertRedirect();
    }

    public function test_stub_provider_remains_bound_in_testing(): void
    {
        $this->assertSame('stub', config('editorial.ai.driver'));
        $this->assertInstanceOf(StubAiProvider::class, app(AiProviderInterface::class));
        ['user' => $user, 'organization' => $organization] = $this->createUserWithOrg();
        $project = app(ProjectService::class)->create($organization, $user, 'Stub Project');
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organization->id, $project->id, $user);
        }
        $source = app(SourceRegistryService::class)->create($organization->id, $project->id, [
            'slug' => 'stub-src',
            'name' => 'Stub',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/stub-'.uniqid().'.xml',
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);
        $announcement = Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source['id'],
            'identity_hash' => hash('sha256', uniqid('id', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/s-'.uniqid(),
            'raw_title' => 'Stub Title',
            'content_hash' => hash('sha256', uniqid('c', true)),
            'raw_payload' => ['title' => 'Stub Title', 'summary' => 'Summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $out = app(GenerateArticlePreviewService::class)->generate(
            $organization->id,
            $project->id,
            $announcement->id,
            $user->id,
        );
        $this->assertTrue($out['ok']);
    }
}
