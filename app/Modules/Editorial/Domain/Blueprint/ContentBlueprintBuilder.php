<?php

namespace App\Modules\Editorial\Domain\Blueprint;


/**
 * Builds a draft Content Blueprint from an announcement snapshot.
 * No prompts, providers, or persistence.
 */
final class ContentBlueprintBuilder
{
    /**
     * @param array<string, mixed> $announcementSnapshot
     * @param array<string, mixed> $overrides
     * @return ContentBlueprint
     */
    public function buildFromAnnouncement(array $announcementSnapshot, array $overrides = array())
    {
        $announcementId = isset($announcementSnapshot['announcement_id'])
            ? trim((string) $announcementSnapshot['announcement_id'])
            : '';
        $rawTitle = isset($announcementSnapshot['raw_title'])
            ? trim((string) $announcementSnapshot['raw_title'])
            : '';
        $articleType = isset($overrides['article_type'])
            && ArticleType::isValid((string) $overrides['article_type'])
            ? (string) $overrides['article_type']
            : ArticleType::NEWS_BRIEF;
        $now = $this->utcNow();
        $preset = $this->presetForArticleType($articleType, $rawTitle);

        $data = array_merge(
            $preset,
            array(
                'blueprint_id' => $this->newBlueprintId($announcementId),
                'announcement_id' => $announcementId,
                'lineage_id' => isset($overrides['lineage_id'])
                    ? (string) $overrides['lineage_id']
                    : '',
                'blueprint_revision' => 1,
                'source_content_hash' => isset($announcementSnapshot['source_content_hash'])
                    ? (string) $announcementSnapshot['source_content_hash']
                    : '',
                'announcement_revision_no' => isset($announcementSnapshot['announcement_revision_no'])
                    ? (int) $announcementSnapshot['announcement_revision_no']
                    : 1,
                'created_by' => isset($overrides['created_by'])
                    ? (string) $overrides['created_by']
                    : 'system',
                'status' => BlueprintStatus::DRAFT,
                'article_type' => $articleType,
                'language' => isset($overrides['language'])
                    ? (string) $overrides['language']
                    : (isset($announcementSnapshot['language'])
                        ? (string) $announcementSnapshot['language']
                        : 'el'),
                'created_at_utc' => $now,
                'updated_at_utc' => $now,
            )
        );

        if (isset($overrides['target_audience'])) {
            $data['target_audience'] = (string) $overrides['target_audience'];
        }

        if (isset($overrides['tone']) && Tone::isValid((string) $overrides['tone'])) {
            $data['tone'] = (string) $overrides['tone'];
        }

        if (isset($overrides['title_candidates']) && is_array($overrides['title_candidates'])) {
            $data['title_candidates'] = $overrides['title_candidates'];
        }

        return new ContentBlueprint($data);
    }

    /**
     * @param string $articleType
     * @param string $rawTitle
     * @return array<string, mixed>
     */
    private function presetForArticleType($articleType, $rawTitle)
    {
        $titleCandidates = $rawTitle !== '' ? array($rawTitle) : array();

        if ($articleType === ArticleType::FAQ_ARTICLE) {
            return array(
                'target_audience' => 'prospective_students',
                'tone' => Tone::ACCESSIBLE,
                'title_candidates' => $titleCandidates,
                'target_length' => array(
                    'unit' => LengthTarget::UNIT_WORDS,
                    'min' => 400,
                    'max' => 1200,
                    'ideal' => 700,
                ),
                'heading_hierarchy' => array(
                    array(
                        'level' => 2,
                        'heading_key' => 'overview',
                        'title' => 'Overview',
                        'required' => true,
                    ),
                    array(
                        'level' => 2,
                        'heading_key' => 'faq',
                        'title' => 'Frequently asked questions',
                        'required' => true,
                    ),
                ),
                'required_sections' => array(
                    array(
                        'section_key' => 'overview',
                        'purpose' => 'Summarize the announcement',
                        'required' => true,
                        'min_words' => 40,
                    ),
                    array(
                        'section_key' => 'faq',
                        'purpose' => 'Answer common questions',
                        'required' => true,
                        'min_words' => 80,
                    ),
                ),
                'faq_requirements' => array(
                    'enabled' => true,
                    'min_items' => 3,
                    'max_items' => 8,
                    'topics' => array(),
                ),
                'cta_requirements' => array(
                    array(
                        'cta_key' => 'read_source',
                        'placement' => 'end',
                        'required' => true,
                        'label_hint' => 'Read the official announcement',
                    ),
                ),
                'internal_link_requirements' => array(),
                'external_reference_requirements' => array(
                    array(
                        'kind' => LinkRequirement::KIND_EXTERNAL,
                        'requirement_key' => 'source_announcement',
                        'required' => true,
                        'min_count' => 1,
                        'target_hint' => 'canonical_url',
                    ),
                ),
                'schema_requirements' => array(
                    array('schema_type' => 'FAQPage', 'required' => true),
                    array('schema_type' => 'Article', 'required' => true),
                ),
                'seo_constraints' => array(
                    'primary_topic' => $rawTitle,
                    'must_include_terms' => array(),
                    'avoid_terms' => array(),
                    'meta_description_required' => true,
                ),
                'validation_rules' => array(
                    array(
                        'rule_id' => 'structure.required_section',
                        'blocking' => true,
                        'params' => array('section_key' => 'faq'),
                    ),
                ),
            );
        }

        return array(
            'target_audience' => 'prospective_students',
            'tone' => Tone::NEUTRAL,
            'title_candidates' => $titleCandidates,
            'target_length' => array(
                'unit' => LengthTarget::UNIT_WORDS,
                'min' => 300,
                'max' => 900,
                'ideal' => 550,
            ),
            'heading_hierarchy' => array(
                array(
                    'level' => 2,
                    'heading_key' => 'summary',
                    'title' => 'Summary',
                    'required' => true,
                ),
                array(
                    'level' => 2,
                    'heading_key' => 'details',
                    'title' => 'Details',
                    'required' => true,
                ),
            ),
            'required_sections' => array(
                array(
                    'section_key' => 'summary',
                    'purpose' => 'Lead summary of the announcement',
                    'required' => true,
                    'min_words' => 40,
                ),
                array(
                    'section_key' => 'details',
                    'purpose' => 'Expand key facts',
                    'required' => true,
                    'min_words' => 80,
                ),
            ),
            'faq_requirements' => array('enabled' => false, 'min_items' => 0, 'max_items' => 0),
            'cta_requirements' => array(
                array(
                    'cta_key' => 'read_source',
                    'placement' => 'end',
                    'required' => true,
                    'label_hint' => 'Read the official announcement',
                ),
            ),
            'internal_link_requirements' => array(),
            'external_reference_requirements' => array(
                array(
                    'kind' => LinkRequirement::KIND_EXTERNAL,
                    'requirement_key' => 'source_announcement',
                    'required' => true,
                    'min_count' => 1,
                    'target_hint' => 'canonical_url',
                ),
            ),
            'schema_requirements' => array(
                array('schema_type' => 'Article', 'required' => true),
            ),
            'seo_constraints' => array(
                'primary_topic' => $rawTitle,
                'must_include_terms' => array(),
                'avoid_terms' => array(),
                'meta_description_required' => true,
            ),
            'validation_rules' => array(
                array(
                    'rule_id' => 'structure.required_section',
                    'blocking' => true,
                    'params' => array('section_key' => 'summary'),
                ),
            ),
        );
    }

    /**
     * @param string $announcementId
     * @return string
     */
    private function newBlueprintId($announcementId)
    {
        return 'bp_' . $announcementId . '_' . substr(hash('sha256', uniqid('', true)), 0, 12);
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
