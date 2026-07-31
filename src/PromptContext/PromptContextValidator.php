<?php

namespace StudyMentor\ContentEngine\PromptContext;

defined('ABSPATH') || exit;

/**
 * Structural validator for PromptContext aggregates.
 * Does not render prompts or call providers.
 */
final class PromptContextValidator
{
    /**
     * @param PromptContext $context
     * @return array{valid: bool, ready: bool, errors: array<int, string>}
     */
    public function validate(PromptContext $context)
    {
        $errors = array();

        if ($context->contextId() === '') {
            $errors[] = 'context_id_required';
        }

        if ($context->announcementId() <= 0) {
            $errors[] = 'announcement_id_required';
        }

        if ($context->blueprintId() === '') {
            $errors[] = 'blueprint_id_required';
        }

        if ($context->blueprintRevision() < 1) {
            $errors[] = 'blueprint_revision_invalid';
        }

        if (!PromptContextStatus::isValid($context->status())) {
            $errors[] = 'status_invalid';
        }

        if ($context->contextHash() === '' || strlen($context->contextHash()) !== 64) {
            $errors[] = 'context_hash_invalid';
        }

        $facts = $context->facts();
        if ($facts->announcementId() <= 0) {
            $errors[] = 'facts_announcement_id_required';
        }

        if (
            $context->announcementId() > 0
            && $facts->announcementId() > 0
            && $facts->announcementId() !== $context->announcementId()
        ) {
            $errors[] = 'facts_announcement_id_mismatch';
        }

        if (trim($facts->rawTitle()) === '') {
            $errors[] = 'facts_raw_title_required';
        }

        if ($facts->contentHash() === '') {
            $errors[] = 'facts_content_hash_required';
        }

        if (
            $context->sourceContentHash() !== ''
            && $facts->contentHash() !== ''
            && $facts->contentHash() !== $context->sourceContentHash()
        ) {
            $errors[] = 'content_hash_mismatch';
        }

        $projection = $context->blueprintProjection();
        if ($projection->blueprintId() === '') {
            $errors[] = 'projection_blueprint_id_required';
        }

        if (
            $context->blueprintId() !== ''
            && $projection->blueprintId() !== ''
            && $projection->blueprintId() !== $context->blueprintId()
        ) {
            $errors[] = 'projection_blueprint_id_mismatch';
        }

        if ($projection->articleType() === '') {
            $errors[] = 'projection_article_type_required';
        }

        if (trim($projection->targetAudience()) === '') {
            $errors[] = 'projection_target_audience_required';
        }

        if (trim($projection->language()) === '') {
            $errors[] = 'projection_language_required';
        }

        if (
            $projection->sectionKeys() === array()
            && $projection->headingKeys() === array()
        ) {
            $errors[] = 'projection_structure_required';
        }

        $errors = array_values(array_unique($errors));
        $valid = $errors === array();
        $ready = $valid
            && $context->status() !== PromptContextStatus::SUPERSEDED
            && $context->sourceContentHash() !== '';

        return array(
            'valid' => $valid,
            'ready' => $ready,
            'errors' => $errors,
        );
    }

    /**
     * @param PromptContext $context
     * @return bool
     */
    public function isStructurallyValid(PromptContext $context)
    {
        $result = $this->validate($context);

        return $result['valid'] === true;
    }

    /**
     * @param PromptContext $context
     * @return bool
     */
    public function canMarkReady(PromptContext $context)
    {
        $result = $this->validate($context);

        return $result['ready'] === true;
    }
}
