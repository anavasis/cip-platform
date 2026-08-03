<?php

namespace Tests\Unit\Modules\Editorial\GenerationResult;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GeneratedArtifactReference;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultBuilder;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultStatus;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultValidator;
use App\Modules\Editorial\Domain\GenerationResult\ProviderExecutionReference;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use Tests\TestCase;

class GenerationResultDomainTest extends TestCase
{
    private function readyRequest()
    {
        $snapshot = [
            'announcement_id' => '44444444-4444-4444-4444-444444444444',
            'raw_title' => 'Result title',
            'source_content_hash' => str_repeat('1', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Result summary.',
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

        return (new GenerationRequestBuilder)->buildFromPackage(
            $package,
            new GenerationModelReference([
                'model_id' => 'smce.stub.deterministic',
                'model_version' => '1',
            ]),
            new GenerationParameters([
                'temperature' => 0.0,
                'max_output_tokens' => 2048,
                'response_format' => GenerationParameters::FORMAT_TEXT,
                'seed' => 1,
            ])
        );
    }

    public function test_success_result_requires_artifacts_and_gres_prefix(): void
    {
        $request = $this->readyRequest();
        $execution = new ProviderExecutionReference([
            'provider_code' => 'stub.deterministic',
            'execution_id' => 'exec_1',
            'started_at_utc' => gmdate('Y-m-d H:i:s'),
            'completed_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
        $artifact = new GeneratedArtifactReference([
            'artifact_id' => 'stub_art_1',
            'artifact_kind' => GeneratedArtifactReference::KIND_CONTENT_CANDIDATE,
            'content_hash' => str_repeat('2', 64),
            'mime_type' => 'text/plain',
        ]);
        $result = (new GenerationResultBuilder)->buildSuccessFromRequest($request, $execution, [$artifact], [
            'duration_ms' => 5,
        ]);
        $validator = new GenerationResultValidator;

        $this->assertSame(GenerationResultStatus::SUCCESS, $result->status());
        $this->assertStringStartsWith('gres_', $result->resultId());
        $this->assertSame(64, strlen($result->resultHash()));
        $this->assertTrue($validator->validate($result)['valid']);
        $this->assertCount(1, $result->artifacts());
    }

    public function test_error_result_requires_error_code_and_no_artifacts(): void
    {
        $request = $this->readyRequest();
        $execution = new ProviderExecutionReference([
            'provider_code' => 'stub.deterministic',
            'execution_id' => 'exec_err',
            'started_at_utc' => gmdate('Y-m-d H:i:s'),
            'completed_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
        $result = (new GenerationResultBuilder)->buildErrorFromRequest(
            $request,
            $execution,
            'provider_failed',
            'provider said no',
            ['duration_ms' => 3]
        );
        $validator = new GenerationResultValidator;

        $this->assertSame(GenerationResultStatus::ERROR, $result->status());
        $this->assertSame('provider_failed', $result->errorCode());
        $this->assertSame([], $result->artifacts());
        $this->assertTrue($validator->validate($result)['valid']);
    }
}
