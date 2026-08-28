<?php

namespace Tests\Unit\Modules\Delivery;

use App\Application\Services\SecretService;
use App\Infrastructure\Persistence\Models\AuditEvent;
use App\Infrastructure\Persistence\Models\ConnectorType;
use App\Infrastructure\Persistence\Models\ProjectConnector;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Delivery\Application\WordPressDraftPublisher;
use App\Modules\Intelligence\Domain\ContentIntelligencePlan;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WordPressDraftPublisherTest extends TestCase
{
    public function test_availability_with_valid_connector_and_secret_is_available(): void
    {
        Http::fake();
        $ctx = $this->wordpressContext();

        $publisher = app(WordPressDraftPublisher::class);
        $availability = $publisher->availability($ctx['organization_id'], $ctx['project_id']);

        $this->assertTrue($availability['available']);
        $this->assertNull($availability['reason']);
        Http::assertNothingSent();
    }

    public function test_availability_does_not_reveal_secret(): void
    {
        Http::fake();
        $ctx = $this->wordpressContext();
        $publisher = app(WordPressDraftPublisher::class);

        $before = $this->revealedAuditCount();
        $publisher->availability($ctx['organization_id'], $ctx['project_id']);
        $after = $this->revealedAuditCount();

        $this->assertSame($before, $after);
    }

    public function test_explicit_create_draft_reveals_secret_once(): void
    {
        Http::fake([
            'https://wp.example.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 1001,
                'link' => 'https://wp.example.test/?p=1001',
                'slug' => 'draft-slug',
            ], 201),
        ]);

        $ctx = $this->wordpressContext();
        $entity = $this->entity($ctx);
        $publisher = app(WordPressDraftPublisher::class);

        $beforeAvailability = $this->revealedAuditCount();
        $publisher->availability($ctx['organization_id'], $ctx['project_id']);
        $this->assertSame($beforeAvailability, $this->revealedAuditCount());

        $beforeCreate = $this->revealedAuditCount();
        $result = $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Draft title',
            'Draft body',
            'draft-slug',
            $entity,
            $ctx['user'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame($beforeCreate + 1, $this->revealedAuditCount());
    }

    public function test_create_new_uses_correct_basic_auth(): void
    {
        Http::fake([
            'https://wp.example.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 90210,
                'link' => 'https://wp.example.test/?p=90210',
                'slug' => 'draft-slug',
            ], 201),
        ]);

        $ctx = $this->wordpressContext();
        $entity = $this->entity($ctx);
        $expectedAuth = 'Basic '.base64_encode('editor:app-password-secret');

        app(WordPressDraftPublisher::class)->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Draft title',
            'Draft body',
            'draft-slug',
            $entity,
            $ctx['user'],
        );

        Http::assertSent(function (Request $request) use ($expectedAuth): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/wp-json/wp/v2/posts')
                && $request->hasHeader('Authorization', $expectedAuth);
        });
    }

    public function test_first_create_new_creates_one_draft(): void
    {
        Http::fake([
            'https://wp.example.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 777,
                'link' => 'https://wp.example.test/?p=777',
                'slug' => 'stored-slug',
            ], 201),
        ]);

        $ctx = $this->wordpressContext();
        $entity = $this->entity($ctx);

        $result = app(WordPressDraftPublisher::class)->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Title',
            'Body',
            'stored-slug',
            $entity,
            $ctx['user'],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('777', $result['remote_post_id']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/wp-json/wp/v2/posts')
                && ($request->data()['status'] ?? null) === 'draft';
        });
        Http::assertSentCount(1);

        $binding = RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->first();

        $this->assertNotNull($binding);
        $this->assertSame('777', $binding->remote_post_id);
        $this->assertNull($binding->confirmed_at);
        $this->assertNull($binding->last_synced_at);
    }

    public function test_retry_existing_unconfirmed_remote_post_id_is_blocked(): void
    {
        Http::fake([
            'https://wp.example.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 501,
                'link' => 'https://wp.example.test/?p=501',
                'slug' => 'stored-slug',
            ], 201),
        ]);

        $ctx = $this->wordpressContext();
        $entity = $this->entity($ctx);
        $publisher = app(WordPressDraftPublisher::class);

        $first = $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Title',
            'Body',
            'stored-slug',
            $entity,
            $ctx['user'],
        );
        $this->assertTrue($first['ok']);

        $binding = RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->firstOrFail();
        $this->assertSame('501', $binding->remote_post_id);

        $auditBeforeRetry = $this->revealedAuditCount();
        Http::fake();

        $retry = $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Title retry',
            'Body retry',
            'stored-slug',
            $entity,
            $ctx['user'],
        );

        $this->assertFalse($retry['ok']);
        $this->assertSame(WordPressDraftPublisher::ERROR_DRAFT_ALREADY_EXISTS, $retry['reason']);
        Http::assertNothingSent();
        $this->assertSame($auditBeforeRetry, $this->revealedAuditCount());
        $this->assertSame('501', $binding->fresh()->remote_post_id);
    }

    public function test_update_existing_zero_http(): void
    {
        Http::fake();

        $ctx = $this->wordpressContext();
        $entity = $this->entity($ctx);
        $publisher = app(WordPressDraftPublisher::class);

        $result = $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_UPDATE_EXISTING,
            'Title',
            'Body',
            'slug',
            $entity,
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(WordPressDraftPublisher::ERROR_ACTION_NOT_CREATE_NEW, $result['reason']);
        Http::assertNothingSent();
    }

    public function test_missing_connector_zero_http(): void
    {
        Http::fake();
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = \App\Infrastructure\Persistence\Models\Project::create([
            'organization_id' => $organization->id,
            'name' => 'WP Missing Connector',
            'slug' => 'wp-missing-'.uniqid(),
            'created_by' => $owner->id,
        ]);
        $entity = ContentEntityModel::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'entity_id' => 'entity-without-wp',
            'entity_type' => 'contest',
            'label' => 'Entity',
            'content_role' => 'satellite',
            'lifecycle_status' => 'open',
            'verification_status' => 'verification_required',
            'hub_member' => false,
            'archive_state' => 'active',
            'publish_eligible' => false,
        ]);

        $publisher = app(WordPressDraftPublisher::class);
        $availability = $publisher->availability($organization->id, $project->id);
        $this->assertFalse($availability['available']);
        $this->assertSame(WordPressDraftPublisher::ERROR_CONNECTOR_UNAVAILABLE, $availability['reason']);

        $result = $publisher->createDraft(
            $organization->id,
            $project->id,
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Title',
            'Body',
            'slug',
            $entity,
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(WordPressDraftPublisher::ERROR_CONNECTOR_UNAVAILABLE, $result['reason']);
        Http::assertNothingSent();
    }

    private function revealedAuditCount(): int
    {
        return AuditEvent::query()->where('action', 'secret.revealed')->count();
    }

    /**
     * @return array{organization_id: string, project_id: string, user: User}
     */
    private function wordpressContext(): array
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = \App\Infrastructure\Persistence\Models\Project::create([
            'organization_id' => $organization->id,
            'name' => 'WP Project',
            'slug' => 'wp-project-'.uniqid(),
            'created_by' => $owner->id,
        ]);

        $connectorType = ConnectorType::query()->firstOrFail();
        ProjectConnector::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'connector_type_id' => $connectorType->id,
            'name' => 'WordPress Site',
            'config' => [
                'provider' => 'wordpress',
                'site_url' => 'https://wp.example.test',
                'username' => 'editor',
            ],
            'enabled' => true,
        ]);

        app(SecretService::class)->create(
            $organization->id,
            'wordpress_app_password',
            'app-password-secret',
            $project->id,
            $owner,
        );

        return [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user' => $owner,
        ];
    }

    /**
     * @param  array{organization_id: string, project_id: string}  $ctx
     */
    private function entity(array $ctx): ContentEntityModel
    {
        return ContentEntityModel::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $ctx['organization_id'],
            'project_id' => $ctx['project_id'],
            'entity_id' => 'wp-entity-'.Str::lower(Str::random(6)),
            'entity_type' => 'contest',
            'label' => 'WP Entity',
            'content_role' => 'satellite',
            'lifecycle_status' => 'open',
            'verification_status' => 'verification_required',
            'hub_member' => false,
            'archive_state' => 'active',
            'publish_eligible' => false,
        ]);
    }
}
