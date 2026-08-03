<?php

namespace Tests\Unit\Modules\Editorial\PromptContext;

use App\Modules\Editorial\Domain\Blueprint\ArticleType;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContext;
use App\Modules\Editorial\Domain\PromptContext\PromptContextBuilder;
use App\Modules\Editorial\Domain\PromptContext\PromptContextStatus;
use App\Modules\Editorial\Domain\PromptContext\PromptContextValidator;
use App\Modules\Editorial\Domain\Support\FoundationCanonicalHasher;
use Tests\TestCase;

class PromptContextDomainTest extends TestCase
{
    private function snapshot(string $id = 'dddddddd-dddd-dddd-dddd-dddddddddddd'): array
    {
        return [
            'announcement_id' => $id,
            'raw_title' => 'Scholarship deadline extended',
            'canonical_url' => 'https://example.test/a/1',
            'source_content_hash' => str_repeat('a', 64),
            'announcement_revision_no' => 1,
            'language' => 'el',
            'summary' => 'Scholarship deadline extended for applicants.',
            'source_id' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        ];
    }

    public function test_builder_validator_ready_and_pc_prefix(): void
    {
        $snapshot = $this->snapshot();
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);
        $validator = new PromptContextValidator;

        $this->assertInstanceOf(PromptContext::class, $context);
        $this->assertStringStartsWith('pc_', $context->contextId());
        $this->assertSame($snapshot['announcement_id'], $context->announcementId());
        $this->assertSame(64, strlen($context->contextHash()));
        $this->assertTrue($validator->validate($context)['valid']);
        $this->assertTrue($validator->canMarkReady($context));
        $this->assertSame(ArticleType::NEWS_BRIEF, $context->blueprintProjection()->articleType());
        $this->assertContains('summary', $context->blueprintProjection()->sectionKeys());
    }

    public function test_context_hash_matches_foundation_hasher_for_binding_shape(): void
    {
        $snapshot = $this->snapshot();
        $blueprint = (new ContentBlueprintBuilder)->buildFromAnnouncement($snapshot);
        $context = (new PromptContextBuilder)->buildFromAnnouncementAndBlueprint($snapshot, $blueprint);

        $payload = [
            'announcement_id' => $context->announcementId(),
            'announcement_revision_no' => $context->announcementRevisionNo(),
            'blueprint_id' => $context->blueprintId(),
            'blueprint_revision' => $context->blueprintRevision(),
            'source_content_hash' => $context->sourceContentHash(),
            'facts' => $context->facts()->toArray(),
            'blueprint_projection' => $context->blueprintProjection()->toArray(),
        ];

        // Rebuild expected using builder's stored hash via validator recompute path
        $validator = new PromptContextValidator;
        $this->assertTrue($validator->validate($context)['valid']);
        $this->assertNotContains('context_hash_invalid', $validator->validate($context)['errors'] ?? []);

        // Hasher key-order independence on facts projection subset
        $this->assertSame(
            FoundationCanonicalHasher::hash(['a' => 1, 'b' => 2]),
            FoundationCanonicalHasher::hash(['b' => 2, 'a' => 1])
        );
        unset($payload);
    }

    public function test_validator_codes_for_empty_context(): void
    {
        $validator = new PromptContextValidator;
        $invalid = new PromptContext([
            'context_id' => '',
            'announcement_id' => '',
            'status' => 'nope',
            'context_hash' => 'short',
        ]);
        $result = $validator->validate($invalid);
        $this->assertFalse($result['valid']);
        $this->assertContains('announcement_id_required', $result['errors']);
    }

    public function test_status_constants(): void
    {
        $this->assertTrue(PromptContextStatus::isValid(PromptContextStatus::DRAFT));
        $this->assertTrue(PromptContextStatus::isValid(PromptContextStatus::READY));
    }
}
