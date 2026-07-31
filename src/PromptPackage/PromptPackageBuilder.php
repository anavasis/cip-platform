<?php

namespace StudyMentor\ContentEngine\PromptPackage;

use StudyMentor\ContentEngine\PromptContext\PromptContext;

defined('ABSPATH') || exit;

/**
 * Builds an immutable Prompt Package from PromptContext + Blueprint reference.
 * Provider-independent binding only — no prompt text, templates body, or rendering.
 */
final class PromptPackageBuilder
{
    /**
     * @param PromptContext $context
     * @param BlueprintReference $blueprintReference
     * @param PromptTemplateReference $templateReference
     * @param array<string, mixed> $overrides
     * @return PromptPackage
     *
     * @throws \InvalidArgumentException when announcement/blueprint identity diverges
     */
    public function buildFromContextAndBlueprint(
        PromptContext $context,
        BlueprintReference $blueprintReference,
        PromptTemplateReference $templateReference,
        array $overrides = array()
    ) {
        $this->assertIdentityAlignment($context, $blueprintReference);

        $now = $this->utcNow();
        $bindingPayload = $this->bindingPayload(
            $context,
            $blueprintReference,
            $templateReference
        );
        $packageHash = $this->hashPayload($bindingPayload);

        $data = array(
            'package_id' => isset($overrides['package_id'])
                ? (string) $overrides['package_id']
                : $this->newPackageId($context->announcementId(), $packageHash),
            'announcement_id' => $context->announcementId(),
            'context_id' => $context->contextId(),
            'context_hash' => $context->contextHash(),
            'blueprint_reference' => $blueprintReference,
            'template_reference' => $templateReference,
            'status' => PromptPackageStatus::SEALED,
            'package_hash' => $packageHash,
            'created_at_utc' => $now,
            'sealed_at_utc' => $now,
        );

        return new PromptPackage($data);
    }

    /**
     * Convenience: derive BlueprintReference from a PromptContext projection binding.
     *
     * @param PromptContext $context
     * @return BlueprintReference
     */
    public function blueprintReferenceFromContext(PromptContext $context)
    {
        return new BlueprintReference(array(
            'blueprint_id' => $context->blueprintId(),
            'blueprint_revision' => $context->blueprintRevision(),
            'announcement_id' => $context->announcementId(),
        ));
    }

    /**
     * @param PromptContext $context
     * @param BlueprintReference $blueprintReference
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertIdentityAlignment(
        PromptContext $context,
        BlueprintReference $blueprintReference
    ) {
        if ($context->announcementId() <= 0) {
            throw new \InvalidArgumentException('announcement_id_required');
        }

        if ($context->contextId() === '' || $context->contextHash() === '') {
            throw new \InvalidArgumentException('context_identity_required');
        }

        if ($blueprintReference->blueprintId() === '') {
            throw new \InvalidArgumentException('blueprint_id_required');
        }

        if ($blueprintReference->blueprintRevision() < 1) {
            throw new \InvalidArgumentException('blueprint_revision_invalid');
        }

        if ($blueprintReference->blueprintId() !== $context->blueprintId()) {
            throw new \InvalidArgumentException('blueprint_id_mismatch');
        }

        if ($blueprintReference->blueprintRevision() !== $context->blueprintRevision()) {
            throw new \InvalidArgumentException('blueprint_revision_mismatch');
        }

        if (
            $blueprintReference->announcementId() > 0
            && $blueprintReference->announcementId() !== $context->announcementId()
        ) {
            throw new \InvalidArgumentException('announcement_id_mismatch');
        }
    }

    /**
     * Canonical binding payload for deterministic package_hash.
     * Excludes package_id and timestamps so identical inputs yield identical hashes.
     *
     * @param PromptContext $context
     * @param BlueprintReference $blueprintReference
     * @param PromptTemplateReference $templateReference
     * @return array<string, mixed>
     */
    private function bindingPayload(
        PromptContext $context,
        BlueprintReference $blueprintReference,
        PromptTemplateReference $templateReference
    ) {
        return array(
            'announcement_id' => $context->announcementId(),
            'context_id' => $context->contextId(),
            'context_hash' => $context->contextHash(),
            'blueprint_reference' => $blueprintReference->toArray(),
            'template_reference' => $templateReference->toArray(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    private function hashPayload(array $payload)
    {
        $canonical = $this->canonicalize($payload);
        $encoded = false;

        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($canonical);
        }

        if (!is_string($encoded) || $encoded === '') {
            $encoded = json_encode($canonical);
        }

        if (!is_string($encoded) || $encoded === '') {
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value !== array() && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = $this->canonicalize($item);
        }

        return $out;
    }

    /**
     * @param int $announcementId
     * @param string $packageHash
     * @return string
     */
    private function newPackageId($announcementId, $packageHash)
    {
        return 'pp_' . (int) $announcementId . '_' . substr((string) $packageHash, 0, 12);
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        if (function_exists('current_time')) {
            return (string) current_time('mysql', true);
        }

        return gmdate('Y-m-d H:i:s');
    }
}
