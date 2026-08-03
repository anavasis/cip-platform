<?php

namespace Tests\Unit\Modules\Editorial\Generation;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use App\Modules\Editorial\Infrastructure\Generation\StubAiProvider;
use Tests\TestCase;

class StubAiProviderTest extends TestCase
{
    public function test_deterministic_offline_success_contract(): void
    {
        $snapshot = [
            'announcement_id' => '55555555-5555-5555-5555-555555555555',
            'raw_title' => 'Stub title',
            'source_content_hash' => str_repeat('9', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Stub summary.',
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
            new GenerationModelReference([
                'model_id' => 'smce.stub.deterministic',
                'model_version' => '1',
            ]),
            new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => 'text',
                'seed' => 1,
            ])
        );

        $provider = new StubAiProvider;
        $a = $provider->generate($request);
        $b = $provider->generate($request);

        $this->assertTrue($a['ok']);
        $this->assertSame(StubAiProvider::PROVIDER_CODE, $a['provider_code']);
        $this->assertSame($a, $b);
        $this->assertArrayHasKey('content_text', $a);
        $this->assertArrayHasKey('execution_id', $a);
        $this->assertArrayHasKey('duration_ms', $a);
        $this->assertArrayHasKey('artifact_id', $a);
        $this->assertStringNotContainsString('http', strtolower($a['content_text']));
    }
}
