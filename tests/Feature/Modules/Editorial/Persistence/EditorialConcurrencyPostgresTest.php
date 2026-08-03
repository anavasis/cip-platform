<?php

namespace Tests\Feature\Modules\Editorial\Persistence;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\Article\ArticlePreview;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentArticlePreviewRepository;
use App\Modules\Editorial\Infrastructure\Persistence\Repositories\EloquentGenerationRequestRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialConcurrencyPostgresTest extends TestCase
{
    /** @return array<int, string> */
    protected function connectionsToTransact(): array
    {
        $connection = (string) config('database.default');

        return config("database.connections.{$connection}.driver") === 'pgsql'
            ? []
            : [$connection];
    }

    public function test_concurrent_duplicate_request_hash_inserts_once(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency test');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('pcntl/sockets required');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Conc Project');
        $source = $this->createSource($organization->id, $project->id, 'conc-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Conc');

        $snapshot = [
            'announcement_id' => $ann->id,
            'raw_title' => 'Conc',
            'source_content_hash' => str_repeat('e', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Conc summary',
        ];
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $package = (new PromptPackageBuilder)->buildFromContextAndBlueprint(
            $context,
            new BlueprintReference([
                'blueprint_id' => $blueprint->blueprintId(),
                'blueprint_revision' => $blueprint->blueprintRevision(),
                'announcement_id' => $blueprint->announcementId(),
            ]),
            new PromptTemplateReference([
                'template_id' => 'smce.editorial.slice_a',
                'template_version' => '1.0.0',
            ])
        );
        $request = (new GenerationRequestBuilder)->buildFromPackage(
            $package,
            new GenerationModelReference(['model_id' => 'smce.stub.deterministic', 'model_version' => '1']),
            new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => 'text',
                'seed' => 1,
            ])
        );

        $payload = $request->toArray();
        $orgId = $organization->id;
        $projectId = $project->id;

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);

        if ($pid === 0) {
            fclose($sockets[0]);
            fgets($sockets[1]);
            try {
                DB::disconnect();
                DB::reconnect();
                $ok = (new EloquentGenerationRequestRepository)->save(
                    $orgId,
                    $projectId,
                    new GenerationRequest($payload)
                );
                fwrite($sockets[1], json_encode(['ok' => $ok], JSON_THROW_ON_ERROR));
                fclose($sockets[1]);
                exit(0);
            } catch (\Throwable $e) {
                fwrite($sockets[1], json_encode(['ok' => false, 'error' => $e->getMessage()]));
                fclose($sockets[1]);
                exit(1);
            }
        }

        fclose($sockets[1]);
        fwrite($sockets[0], "go\n");
        fflush($sockets[0]);
        DB::disconnect();
        DB::reconnect();
        $parentOk = (new EloquentGenerationRequestRepository)->save(
            $orgId,
            $projectId,
            new GenerationRequest($payload)
        );
        $childPayload = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        pcntl_waitpid($pid, $status);
        $child = json_decode((string) $childPayload, true, 512, JSON_THROW_ON_ERROR);

        $count = DB::table('generation_requests')->where('project_id', $projectId)->count();
        $organization->delete();
        $owner->delete();

        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status), (string) ($child['error'] ?? ''));
        $this->assertTrue($parentOk);
        $this->assertTrue((bool) $child['ok']);
        $this->assertSame(1, $count);
    }

    public function test_concurrent_preview_key_inserts_once(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL concurrency test');
        }
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            $this->markTestSkipped('pcntl/sockets required');
        }

        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Prev Project');
        $source = $this->createSource($organization->id, $project->id, 'prev-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Prev');

        $previewData = [
            'preview_id' => 'apv_'.str_repeat('1', 24),
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'announcement_id' => $ann->id,
            'request_id' => 'gr_x',
            'result_id' => 'gres_x',
            'result_hash' => str_repeat('f', 64),
            'title' => 'Prev',
            'body' => 'Body',
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ];

        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid);

        if ($pid === 0) {
            fclose($sockets[0]);
            fgets($sockets[1]);
            try {
                DB::disconnect();
                DB::reconnect();
                $ok = (new EloquentArticlePreviewRepository)->save(new ArticlePreview($previewData));
                fwrite($sockets[1], json_encode(['ok' => $ok], JSON_THROW_ON_ERROR));
                fclose($sockets[1]);
                exit(0);
            } catch (\Throwable $e) {
                fwrite($sockets[1], json_encode(['ok' => false, 'error' => $e->getMessage()]));
                fclose($sockets[1]);
                exit(1);
            }
        }

        fclose($sockets[1]);
        fwrite($sockets[0], "go\n");
        fflush($sockets[0]);
        DB::disconnect();
        DB::reconnect();
        $parentOk = (new EloquentArticlePreviewRepository)->save(new ArticlePreview($previewData));
        $childPayload = stream_get_contents($sockets[0]);
        fclose($sockets[0]);
        pcntl_waitpid($pid, $status);
        $child = json_decode((string) $childPayload, true, 512, JSON_THROW_ON_ERROR);

        $count = DB::table('article_previews')->where('project_id', $project->id)->count();
        $organization->delete();
        $owner->delete();

        $this->assertTrue(pcntl_wifexited($status));
        $this->assertSame(0, pcntl_wexitstatus($status), (string) ($child['error'] ?? ''));
        $this->assertTrue($parentOk);
        $this->assertTrue((bool) $child['ok']);
        $this->assertSame(1, $count);
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
    }

    private function createAnnouncement(string $organizationId, string $projectId, string $sourceId, string $title): Announcement
    {
        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'identity_hash' => hash('sha256', $title.'|'.$projectId.uniqid()),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::slug($title).'-'.uniqid(),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title.uniqid()),
            'raw_payload' => ['title' => $title],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
