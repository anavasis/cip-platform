<?php

namespace Tests\Unit\Modules\Editorial\PromptPackage;

use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptPackage\BlueprintReference;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackage;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageBuilder;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageStatus;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageValidator;
use App\Modules\Editorial\Domain\PromptPackage\PromptTemplateReference;
use Tests\TestCase;

class PromptPackageDomainTest extends TestCase
{
    public function test_seal_package_hash_and_pp_prefix(): void
    {
        $snapshot = [
            'announcement_id' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'raw_title' => 'Package title',
            'source_content_hash' => str_repeat('d', 64),
            'announcement_revision_no' => 1,
            'language' => 'en',
            'summary' => 'Summary text for package.',
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
        $package = (new PromptPackageBuilder)->buildFromContextAndBlueprint($context, $blueprintRef, $template);
        $validator = new PromptPackageValidator;

        $this->assertInstanceOf(PromptPackage::class, $package);
        $this->assertStringStartsWith('pp_', $package->packageId());
        $this->assertSame(PromptPackageStatus::SEALED, $package->status());
        $this->assertNotSame('', $package->sealedAtUtc());
        $this->assertSame(64, strlen($package->packageHash()));
        $this->assertTrue($validator->validate($package)['valid']);
        $this->assertSame('smce.editorial.slice_a', $package->templateReference()->templateId());
        $this->assertSame('1.0.0', $package->templateReference()->templateVersion());
    }

    public function test_identity_mismatch_rejected_by_builder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $snapshot = [
            'announcement_id' => '11111111-1111-1111-1111-111111111111',
            'raw_title' => 'A',
            'source_content_hash' => str_repeat('e', 64),
            'announcement_revision_no' => 1,
        ];
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $other = (new ContentBlueprintBuilder)->buildFromAnnouncement(array_merge($snapshot, [
            'announcement_id' => '22222222-2222-2222-2222-222222222222',
        ]));
        $template = new PromptTemplateReference([
            'template_id' => 'smce.editorial.slice_a',
            'template_version' => '1.0.0',
        ]);
        $badRef = new BlueprintReference([
            'blueprint_id' => $other->blueprintId(),
            'blueprint_revision' => $other->blueprintRevision(),
            'announcement_id' => $other->announcementId(),
        ]);
        (new PromptPackageBuilder)->buildFromContextAndBlueprint($context, $badRef, $template);
    }
}
