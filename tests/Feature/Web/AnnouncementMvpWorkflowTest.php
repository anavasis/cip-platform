<?php

namespace Tests\Feature\Web;

use App\Application\Services\ConfigurationService;
use App\Application\Services\FeatureFlagService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Application\Services\SecretService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\ConnectorType;
use App\Infrastructure\Persistence\Models\ProjectConnector;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Application\CapabilityGate as AcquisitionCapabilityGate;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Intelligence\Application\ContentIntelligencePlanner;
use App\Modules\Intelligence\Application\EntityBindingService;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use App\Support\OperatorContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Acquisition\Support\AcquisitionHttpTestBindings;
use Tests\Feature\Modules\Acquisition\Support\SequencedFeedFetcher;
use Tests\TestCase;

class AnnouncementMvpWorkflowTest extends TestCase
{
    public function test_run_and_ingest_surfaces_acquisition_failure_without_false_success(): void
    {
        AcquisitionHttpTestBindings::bindFeedFetcher($this->app, new SequencedFeedFetcher([
            [
                'success' => false,
                'error_code' => 'network_error',
                'body' => '',
            ],
        ]));

        $ctx = $this->operatorContext();
        $this->enableAcquisition($ctx);
        $source = $this->createSource($ctx);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.sources.run-and-ingest', $source))
            ->assertRedirect()
            ->assertSessionHasErrors('ingest')
            ->assertSessionMissing('status');

        $this->assertSame(0, Announcement::query()->where('source_id', $source->id)->count());
    }

    public function test_run_and_ingest_makes_announcements_available_via_existing_path(): void
    {
        AcquisitionHttpTestBindings::bindFeedFetcher($this->app, new SequencedFeedFetcher([
            [
                'success' => true,
                'body' => $this->rssBody('MVP ingested item', 'https://example.com/mvp-ingested'),
                'content_type' => 'application/rss+xml',
            ],
        ]));

        $ctx = $this->operatorContext();
        $this->enableAcquisition($ctx);
        $source = $this->createSource($ctx);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.sources.run-and-ingest', $source))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('announcements', [
            'project_id' => $ctx['project']->id,
            'source_id' => $source->id,
            'raw_title' => 'MVP ingested item',
            'revision_no' => 1,
        ]);
    }

    public function test_preview_delivery_panel_and_publish_package_download_for_create_new(): void
    {
        Http::fake();
        $ctx = $this->operatorContext();
        $this->enableEditorial($ctx);
        $this->saveProfile($ctx, $this->createNewProfile());
        $announcement = $this->makeAnnouncement($ctx, 'ΑΣΕΠ Νέο 2026');
        app(EntityBindingService::class)->bindAnnouncement($announcement);
        $this->generatePreview($ctx, $announcement);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.preview.show', $announcement))
            ->assertOk()
            ->assertSee('Delivery', false)
            ->assertSee('create_new', false)
            ->assertSee('Download Publish Package', false)
            ->assertSee('WordPress connector unavailable', false);

        $response = $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.delivery.package', $announcement));

        $response->assertOk();
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(1, $payload['schema_version']);
        $this->assertSame('create_new', $payload['content_intelligence']['action']);
        $this->assertNotSame('', $payload['article']['body']);
        $this->assertSame('new-satellite-2026', $payload['entity']['entity_id']);
        Http::assertNothingSent();
    }

    public function test_update_existing_hides_wordpress_draft_but_allows_package_download(): void
    {
        Http::fake();
        $ctx = $this->operatorContext();
        $this->enableEditorial($ctx);
        $this->saveProfile($ctx, $this->updateExistingProfile());
        $announcement = $this->makeAnnouncement($ctx, 'ΑΣΕΠ 6Κ/2026');
        app(EntityBindingService::class)->bindAnnouncement($announcement);
        $this->generatePreview($ctx, $announcement);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.preview.show', $announcement))
            ->assertOk()
            ->assertSee('update_existing', false)
            ->assertSee('Download Publish Package', false)
            ->assertSee('WordPress draft not available for update_existing', false)
            ->assertDontSee('Create WordPress Draft', false);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.delivery.wordpress-draft', $announcement))
            ->assertRedirect()
            ->assertSessionHasErrors('delivery');

        Http::assertNothingSent();
    }

    public function test_wordpress_draft_action_posts_draft_only_when_connector_available(): void
    {
        Http::fake([
            'https://wp.example.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 501,
                'link' => 'https://wp.example.test/?p=501',
                'slug' => 'new-satellite-2026',
            ], 201),
        ]);

        $ctx = $this->operatorContext();
        $this->enableEditorial($ctx);
        $this->saveProfile($ctx, $this->createNewProfile());
        $this->attachWordPressConnector($ctx);
        $announcement = $this->makeAnnouncement($ctx, 'ΑΣΕΠ Νέο 2026');
        app(EntityBindingService::class)->bindAnnouncement($announcement);
        $this->generatePreview($ctx, $announcement);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->get(route('app.preview.show', $announcement))
            ->assertOk()
            ->assertSee('Create WordPress Draft', false);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.delivery.wordpress-draft', $announcement))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/wp-json/wp/v2/posts')
                && ($request->data()['status'] ?? null) === 'draft';
        });
        Http::assertSentCount(1);

        $entity = ContentEntityModel::query()->where('entity_id', 'new-satellite-2026')->firstOrFail();
        $binding = RemotePostBindingModel::query()->where('content_entity_id', $entity->id)->first();
        $this->assertNotNull($binding);
        $this->assertSame('501', $binding->remote_post_id);
        $this->assertNull($binding->confirmed_at);
    }

    public function test_explicit_hub_release_sets_verified_state(): void
    {
        Http::fake();
        $ctx = $this->operatorContext();
        $this->enableEditorial($ctx);
        $this->saveProfile($ctx, $this->updateExistingProfile());
        $announcement = $this->makeAnnouncement($ctx, 'ΑΣΕΠ 6Κ/2026');
        app(EntityBindingService::class)->bindAnnouncement($announcement);
        $this->generatePreview($ctx, $announcement);

        $this->actingAs($ctx['user'])->withSession($this->sessionFor($ctx))
            ->post(route('app.delivery.hub-release', $announcement), [
                'lifecycle_status' => 'results',
                'canonical_url' => 'https://example.test/asep-6k-2026',
                'confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $entity = ContentEntityModel::query()->where('entity_id', 'asep-6k-2026')->firstOrFail();
        $this->assertTrue($entity->hub_member);
        $this->assertTrue($entity->publish_eligible);
        $this->assertSame('verified', $entity->verification_status);
        $this->assertSame('results', $entity->lifecycle_status);

        Http::assertNothingSent();
    }

    private function rssBody(string $title, string $link): string
    {
        return '<?xml version="1.0"?><rss version="2.0"><channel>'.
            '<title>Feed</title><item><title>'.$title.'</title>'.
            '<link>'.$link.'</link><guid>'.$link.'</guid>'.
            '</item></channel></rss>';
    }

    /**
     * @return array{user: User, organization: mixed, project: mixed}
     */
    private function operatorContext(): array
    {
        $user = User::factory()->create(['password' => Hash::make('password-secret')]);
        $organization = app(OrganizationService::class)->create($user, 'MVP Org');
        $project = app(ProjectService::class)->create($organization, $user, 'MVP Project');

        return compact('user', 'organization', 'project');
    }

    /**
     * @param  array{organization: mixed, project: mixed}  $ctx
     * @return array<string, string>
     */
    private function sessionFor(array $ctx): array
    {
        return [
            OperatorContext::SESSION_ORG => $ctx['organization']->id,
            OperatorContext::SESSION_PROJECT => $ctx['project']->id,
        ];
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function enableEditorial(array $ctx): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $ctx['organization']->id, $ctx['project']->id, $ctx['user']);
        }
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function enableAcquisition(array $ctx): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([AcquisitionCapabilityGate::ACQUISITION, AcquisitionCapabilityGate::SOURCE_REGISTRY] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $ctx['organization']->id, $ctx['project']->id, $ctx['user']);
        }
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function createSource(array $ctx): Source
    {
        $feedUrl = 'https://example.com/mvp-'.uniqid().'.xml';

        return Source::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'slug' => 'mvp-source-'.Str::lower(Str::random(4)),
            'name' => 'MVP Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function makeAnnouncement(array $ctx, string $title): Announcement
    {
        $source = $this->createSource($ctx);

        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', uniqid('', true)),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::lower(Str::random(8)),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title),
            'raw_payload' => ['title' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function generatePreview(array $ctx, Announcement $announcement): void
    {
        $result = app(GenerateArticlePreviewService::class)->generate(
            $ctx['organization']->id,
            $ctx['project']->id,
            $announcement->id,
            $ctx['user']->id,
        );
        $this->assertTrue($result['ok']);
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     * @param  array<string, mixed>  $profile
     */
    private function saveProfile(array $ctx, array $profile): void
    {
        app(ConfigurationService::class)->set(
            $ctx['organization']->id,
            ContentIntelligencePlanner::PROFILE_KEY,
            ['value' => $profile],
            $ctx['project']->id,
            $ctx['user'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createNewProfile(): array
    {
        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'example.test',
            'entity_rules' => [
                [
                    'entity_id' => 'new-satellite-2026',
                    'label' => 'Νέο Satellite 2026',
                    'patterns' => ['Νέο\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'parent_hub' => [
                        'entity_id' => 'hub-2026',
                        'label' => 'Hub 2026',
                        'url' => 'https://example.test/hub-2026',
                    ],
                    'seo' => ['slug' => 'new-satellite-2026'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateExistingProfile(): array
    {
        return [
            'version' => 1,
            'publishing_mode' => 'plan_only',
            'primary_domain' => 'example.test',
            'entity_rules' => [
                [
                    'entity_id' => 'asep-6k-2026',
                    'label' => 'ΑΣΕΠ 6Κ/2026',
                    'patterns' => ['6\\s*[ΚK]\\s*\\/\\s*2026'],
                    'match_scope' => 'title',
                    'content_role' => 'satellite',
                    'canonical_target_url' => 'https://example.test/asep-6k-2026',
                    'seo' => ['slug' => 'asep-6k-2026'],
                ],
            ],
        ];
    }

    /**
     * @param  array{user: User, organization: mixed, project: mixed}  $ctx
     */
    private function attachWordPressConnector(array $ctx): void
    {
        $connectorType = ConnectorType::query()->firstOrFail();
        ProjectConnector::create([
            'organization_id' => $ctx['organization']->id,
            'project_id' => $ctx['project']->id,
            'connector_type_id' => $connectorType->id,
            'name' => 'WordPress',
            'config' => [
                'provider' => 'wordpress',
                'site_url' => 'https://wp.example.test',
                'username' => 'editor',
            ],
            'enabled' => true,
        ]);

        app(SecretService::class)->create(
            $ctx['organization']->id,
            'wordpress_app_password',
            'editor:app-password-secret',
            $ctx['project']->id,
            $ctx['user'],
        );
    }
}
