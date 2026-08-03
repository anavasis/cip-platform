<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\SourceAcquisitionService;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcquisitionSsrfTest extends TestCase
{
    public function test_acquisition_blocks_allowlisted_loopback_before_http_request(): void
    {
        Http::fake();
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'SSRF Project',
            'slug' => 'ssrf-project',
            'created_by' => $owner->id,
        ]);
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'loopback',
            'name' => 'Loopback',
            'source_type' => 'rss',
            'base_url' => 'http://127.0.0.1',
            'feed_url' => 'http://127.0.0.1/private-feed',
            'feed_url_hash' => hash('sha256', 'http://127.0.0.1/private-feed'),
            'allowed_domains' => ['127.0.0.1'],
            'enabled' => true,
            'manual_only' => false,
        ]);

        $result = app(SourceAcquisitionService::class)->acquireFromSource(
            $organization->id,
            $project->id,
            $source->id,
        );

        $this->assertFalse($result->success());
        $this->assertSame('url_blocked', $result->errorCode());
        $this->assertSame('', $result->fetchResult()['body']);
        Http::assertNothingSent();
    }
}
