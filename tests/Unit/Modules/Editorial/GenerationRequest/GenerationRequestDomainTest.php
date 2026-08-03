<?php

namespace Tests\Unit\Modules\Editorial\GenerationRequest;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationModelReference;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationParameters;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestBuilder;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestStatus;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestValidator;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use Tests\TestCase;

class GenerationRequestDomainTest extends TestCase
{
    private function sealedPackage(): \App\Modules\Editorial\Domain\PromptPackage\PromptPackage
    {
        $snapshot = [
            'announcement_id' => '33333333-3333-3333-3333-333333333333',
            'raw_title' => 'Request title',
            'source_content_hash' => str_repeat('f', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Request summary.',
        ];
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $template = new PromptTemplateReference([
            'template_id' => 'smce.editorial.slice_a',
            'template_version' => '1.0.0',
        ]);
        $blueprintRef = new BlueprintReference([
            'blueprint_id' => $blueprint->blueprintId(),
            'blueprint_revision' => $blueprint->blueprintRevision(),
            'announcement_id' => $blueprint->announcementId(),
        ]);

        return (new PromptPackageBuilder)->buildFromContextAndBlueprint($context, $blueprintRef, $template);
    }

    public function test_ready_request_hash_and_gr_prefix_with_stub_model_params(): void
    {
        $package = $this->sealedPackage();
        $model = new GenerationModelReference([
            'model_id' => 'smce.stub.deterministic',
            'model_version' => '1',
        ]);
        $params = new GenerationParameters([
            'temperature' => 0.0,
            'max_output_tokens' => 2048,
            'response_format' => GenerationParameters::FORMAT_TEXT,
            'seed' => 1,
        ]);
        $request = (new GenerationRequestBuilder)->buildFromPackage($package, $model, $params);
        $validator = new GenerationRequestValidator;

        $this->assertInstanceOf(GenerationRequest::class, $request);
        $this->assertStringStartsWith('gr_', $request->requestId());
        $this->assertSame(GenerationRequestStatus::READY, $request->status());
        $this->assertSame(64, strlen($request->requestHash()));
        $this->assertTrue($validator->validate($request)['valid']);
        $this->assertSame(0.0, (float) $request->parameters()->temperature());
        $this->assertSame(2048, $request->parameters()->maxOutputTokens());
        $this->assertSame('text', $request->parameters()->responseFormat());
        $this->assertSame(1, $request->parameters()->seed());
    }

    public function test_parameter_range_rejected_by_builder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('temperature_out_of_range');
        $package = $this->sealedPackage();
        $model = new GenerationModelReference([
            'model_id' => 'smce.stub.deterministic',
            'model_version' => '1',
        ]);
        $params = new GenerationParameters([
            'temperature' => 9.0,
            'max_output_tokens' => 2048,
            'response_format' => GenerationParameters::FORMAT_TEXT,
            'seed' => 1,
        ]);
        (new GenerationRequestBuilder)->buildFromPackage($package, $model, $params);
    }

    public function test_validator_flags_temperature_out_of_range_on_manual_request(): void
    {
        $package = $this->sealedPackage();
        $request = new GenerationRequest([
            'request_id' => 'gr_test',
            'announcement_id' => $package->announcementId(),
            'package_id' => $package->packageId(),
            'package_hash' => $package->packageHash(),
            'model_reference' => new GenerationModelReference([
                'model_id' => 'smce.stub.deterministic',
                'model_version' => '1',
            ]),
            'parameters' => new GenerationParameters([
                'temperature' => 9.0,
                'max_output_tokens' => 2048,
                'response_format' => GenerationParameters::FORMAT_TEXT,
                'seed' => 1,
            ]),
            'status' => GenerationRequestStatus::READY,
            'request_hash' => str_repeat('a', 64),
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
        $result = (new GenerationRequestValidator)->validate($request);
        $this->assertFalse($result['valid']);
        $this->assertContains('temperature_out_of_range', $result['errors']);
    }
}
