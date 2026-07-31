<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Structural validator for ContentBlueprint aggregates.
 * Does not validate generated articles (that is the Compliance Engine).
 */
final class ContentBlueprintValidator
{
    /**
     * @param ContentBlueprint $blueprint
     * @return array{valid: bool, ready: bool, errors: array<int, string>}
     */
    public function validate(ContentBlueprint $blueprint)
    {
        $errors = array();

        if ($blueprint->blueprintId() === '') {
            $errors[] = 'blueprint_id_required';
        }

        if ($blueprint->announcementId() <= 0) {
            $errors[] = 'announcement_id_required';
        }

        if (!BlueprintStatus::isValid($blueprint->status())) {
            $errors[] = 'status_invalid';
        }

        if (!ArticleType::isValid($blueprint->articleType())) {
            $errors[] = 'article_type_invalid';
        }

        if (trim($blueprint->targetAudience()) === '') {
            $errors[] = 'target_audience_required';
        }

        if (trim($blueprint->language()) === '') {
            $errors[] = 'language_required';
        }

        if (!Tone::isValid($blueprint->tone())) {
            $errors[] = 'tone_invalid';
        }

        $length = $blueprint->targetLength();
        if (
            $length->unit() !== LengthTarget::UNIT_WORDS
            && $length->unit() !== LengthTarget::UNIT_CHARS
        ) {
            $errors[] = 'target_length_unit_invalid';
        }

        if ($length->min() < 0 || $length->max() < 0 || $length->ideal() < 0) {
            $errors[] = 'target_length_negative';
        }

        if ($length->min() > $length->max()) {
            $errors[] = 'target_length_min_gt_max';
        }

        if ($length->ideal() < $length->min() || $length->ideal() > $length->max()) {
            $errors[] = 'target_length_ideal_out_of_range';
        }

        if (
            $blueprint->requiredSections() === array()
            && $blueprint->headingHierarchy() === array()
        ) {
            $errors[] = 'structure_required';
        }

        foreach ($blueprint->requiredSections() as $section) {
            if (!$section instanceof SectionSpec) {
                $errors[] = 'section_invalid';
                continue;
            }

            if ($section->sectionKey() === '') {
                $errors[] = 'section_key_required';
            }
        }

        foreach ($blueprint->headingHierarchy() as $heading) {
            if (!$heading instanceof HeadingNode) {
                $errors[] = 'heading_invalid';
                continue;
            }

            if ($heading->headingKey() === '' || $heading->level() < 1 || $heading->level() > 6) {
                $errors[] = 'heading_invalid';
            }
        }

        $faq = $blueprint->faqRequirements();
        if ($faq->enabled()) {
            if ($faq->minItems() < 0 || $faq->maxItems() < 0) {
                $errors[] = 'faq_counts_invalid';
            }

            if ($faq->minItems() > $faq->maxItems()) {
                $errors[] = 'faq_min_gt_max';
            }
        }

        foreach ($blueprint->ctaRequirements() as $cta) {
            if (!$cta instanceof CtaRequirement || $cta->ctaKey() === '') {
                $errors[] = 'cta_invalid';
            }
        }

        foreach ($blueprint->schemaRequirements() as $schema) {
            if (!$schema instanceof SchemaRequirement || $schema->schemaType() === '') {
                $errors[] = 'schema_invalid';
            }
        }

        foreach ($blueprint->validationRules() as $rule) {
            if (!$rule instanceof ValidationRuleSpec || $rule->ruleId() === '') {
                $errors[] = 'validation_rule_invalid';
            }
        }

        $errors = array_values(array_unique($errors));
        $valid = $errors === array();
        $ready = $valid
            && $blueprint->status() !== BlueprintStatus::SUPERSEDED
            && $blueprint->sourceContentHash() !== '';

        return array(
            'valid' => $valid,
            'ready' => $ready,
            'errors' => $errors,
        );
    }

    /**
     * @param ContentBlueprint $blueprint
     * @return bool
     */
    public function isStructurallyValid(ContentBlueprint $blueprint)
    {
        $result = $this->validate($blueprint);

        return $result['valid'] === true;
    }

    /**
     * @param ContentBlueprint $blueprint
     * @return bool
     */
    public function canMarkReady(ContentBlueprint $blueprint)
    {
        $result = $this->validate($blueprint);

        return $result['ready'] === true;
    }
}
