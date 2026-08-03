<?php

namespace Tests\Unit\Modules\Editorial\Blueprint;

use App\Modules\Editorial\Domain\Blueprint\ArticleType;
use App\Modules\Editorial\Domain\Blueprint\BlueprintStatus;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprint;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintBuilder;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintValidator;
use App\Modules\Editorial\Domain\Blueprint\Tone;
use Tests\TestCase;

class ContentBlueprintDomainTest extends TestCase
{
    public function test_status_and_enum_validity(): void
    {
        $this->assertTrue(BlueprintStatus::isValid(BlueprintStatus::DRAFT));
        $this->assertTrue(BlueprintStatus::isValid(BlueprintStatus::READY));
        $this->assertFalse(BlueprintStatus::isValid('published'));
        $this->assertTrue(ArticleType::isValid(ArticleType::NEWS_BRIEF));
        $this->assertTrue(Tone::isValid(Tone::NEUTRAL));
    }

    public function test_builder_default_news_brief_and_uuid_announcement(): void
    {
        $announcementId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $builder = new ContentBlueprintBuilder;
        $validator = new ContentBlueprintValidator;

        $blueprint = $builder->buildFromAnnouncement([
            'announcement_id' => $announcementId,
            'raw_title' => 'Scholarship deadline extended',
            'source_content_hash' => str_repeat('a', 64),
            'announcement_revision_no' => 1,
            'language' => 'el',
        ]);

        $this->assertSame($announcementId, $blueprint->announcementId());
        $this->assertSame(BlueprintStatus::DRAFT, $blueprint->status());
        $this->assertSame(ArticleType::NEWS_BRIEF, $blueprint->articleType());
        $this->assertNotSame('', $blueprint->blueprintId());
        $this->assertStringStartsWith('bp_', $blueprint->blueprintId());
        $this->assertStringContainsString($announcementId, $blueprint->blueprintId());
        $this->assertGreaterThanOrEqual(1, count($blueprint->requiredSections()));
        $this->assertGreaterThanOrEqual(1, count($blueprint->headingHierarchy()));
        $this->assertSame('Scholarship deadline extended', $blueprint->titleCandidates()[0]);

        $validation = $validator->validate($blueprint);
        $this->assertTrue($validation['valid']);
        $this->assertTrue($validation['ready']);
        $this->assertTrue($validator->canMarkReady($blueprint));
    }

    public function test_validator_error_codes_and_order_sensitive_failures(): void
    {
        $validator = new ContentBlueprintValidator;
        $invalid = new ContentBlueprint([
            'blueprint_id' => '',
            'announcement_id' => '',
            'status' => 'nope',
            'article_type' => 'unknown',
            'target_audience' => '',
            'language' => '',
            'tone' => 'loud',
            'target_length' => ['unit' => 'words', 'min' => 10, 'max' => 5, 'ideal' => 7],
            'required_sections' => [],
            'heading_hierarchy' => [],
            'source_content_hash' => '',
        ]);
        $result = $validator->validate($invalid);
        $this->assertFalse($result['valid']);
        $this->assertContains('announcement_id_required', $result['errors']);
        $this->assertContains('structure_required', $result['errors']);
        $this->assertFalse($validator->canMarkReady($invalid));
    }

    public function test_faq_article_preset(): void
    {
        $builder = new ContentBlueprintBuilder;
        $validator = new ContentBlueprintValidator;
        $faq = $builder->buildFromAnnouncement(
            [
                'announcement_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'raw_title' => 'FAQ source',
                'source_content_hash' => str_repeat('b', 64),
                'announcement_revision_no' => 2,
            ],
            ['article_type' => ArticleType::FAQ_ARTICLE]
        );
        $this->assertSame(ArticleType::FAQ_ARTICLE, $faq->articleType());
        $this->assertTrue($faq->faqRequirements()->enabled());
        $this->assertTrue($validator->isStructurallyValid($faq));
    }

    public function test_to_array_round_trip(): void
    {
        $builder = new ContentBlueprintBuilder;
        $blueprint = $builder->buildFromAnnouncement([
            'announcement_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'raw_title' => 'Round trip',
            'source_content_hash' => str_repeat('c', 64),
            'announcement_revision_no' => 1,
        ]);
        $rehydrated = new ContentBlueprint($blueprint->toArray());
        $this->assertSame($blueprint->blueprintId(), $rehydrated->blueprintId());
        $this->assertSame($blueprint->announcementId(), $rehydrated->announcementId());
    }
}
