<?php

namespace Tests\Unit\Modules\Delivery;

use App\Application\Services\SecretService;
use App\Infrastructure\Persistence\Models\ConnectorType;
use App\Infrastructure\Persistence\Models\ProjectConnector;
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
    public function test_create_new_sends_post_draft_to_wordpress_posts_endpoint(): void
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

        $publisher = app(WordPressDraftPublisher::class);
        $result = $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Draft title',
            'Draft body',
            'draft-slug',
            $entity,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('90210', $result['remote_post_id']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/wp-json/wp/v2/posts')
                && ($request->data()['status'] ?? null) === 'draft'
                && ($request->data()['title'] ?? null) === 'Draft title';
        });
        Http::assertSentCount(1);
    }

    public function test_update_existing_makes_zero_http_calls(): void
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

    public function test_missing_connector_makes_zero_http_calls(): void
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

    public function test_successful_draft_stores_unconfirmed_remote_post_id(): void
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
        $publisher = app(WordPressDraftPublisher::class);

        $publisher->createDraft(
            $ctx['organization_id'],
            $ctx['project_id'],
            ContentIntelligencePlan::ACTION_CREATE_NEW,
            'Title',
            'Body',
            'stored-slug',
            $entity,
        );

        $binding = RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->first();

        $this->assertNotNull($binding);
        $this->assertSame('777', $binding->remote_post_id);
        $this->assertNull($binding->confirmed_at);
        $this->assertNull($binding->last_synced_at);
    }

    /**
     * @return array{organization_id: string, project_id: string}
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
            'editor:app-password-secret',
            $project->id,
            $owner,
        );

        return [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
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
