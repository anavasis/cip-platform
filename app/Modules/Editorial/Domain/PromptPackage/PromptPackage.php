<?php

namespace App\Modules\Editorial\Domain\PromptPackage;


/**
 * Immutable Prompt Package aggregate (ADR-001).
 * Binds Prompt Context snapshot + Blueprint reference + template catalog ref.
 * Provider-independent — no prompt prose, rendering, or Generation Request.
 */
final class PromptPackage
{
    private $packageId;
    private $announcementId;
    private $contextId;
    private $contextHash;
    private $blueprintReference;
    private $templateReference;
    private $status;
    private $packageHash;
    private $createdAtUtc;
    private $sealedAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->packageId = isset($data['package_id']) ? (string) $data['package_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? trim((string) $data['announcement_id']) : '';
        $this->contextId = isset($data['context_id']) ? (string) $data['context_id'] : '';
        $this->contextHash = isset($data['context_hash']) ? (string) $data['context_hash'] : '';
        $this->blueprintReference = isset($data['blueprint_reference'])
            && $data['blueprint_reference'] instanceof BlueprintReference
            ? $data['blueprint_reference']
            : new BlueprintReference(
                isset($data['blueprint_reference']) && is_array($data['blueprint_reference'])
                    ? $data['blueprint_reference']
                    : array()
            );
        $this->templateReference = isset($data['template_reference'])
            && $data['template_reference'] instanceof PromptTemplateReference
            ? $data['template_reference']
            : new PromptTemplateReference(
                isset($data['template_reference']) && is_array($data['template_reference'])
                    ? $data['template_reference']
                    : array()
            );
        $this->status = isset($data['status'])
            ? (string) $data['status']
            : PromptPackageStatus::SEALED;
        $this->packageHash = isset($data['package_hash']) ? (string) $data['package_hash'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
        $this->sealedAtUtc = isset($data['sealed_at_utc']) ? (string) $data['sealed_at_utc'] : '';
    }

    /** @return string */
    public function packageId()
    {
        return $this->packageId;
    }

    /** @return string */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function contextId()
    {
        return $this->contextId;
    }

    /** @return string */
    public function contextHash()
    {
        return $this->contextHash;
    }

    /** @return BlueprintReference */
    public function blueprintReference()
    {
        return $this->blueprintReference;
    }

    /** @return PromptTemplateReference */
    public function templateReference()
    {
        return $this->templateReference;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return string */
    public function packageHash()
    {
        return $this->packageHash;
    }

    /** @return string */
    public function createdAtUtc()
    {
        return $this->createdAtUtc;
    }

    /** @return string */
    public function sealedAtUtc()
    {
        return $this->sealedAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'package_id' => $this->packageId,
            'announcement_id' => $this->announcementId,
            'context_id' => $this->contextId,
            'context_hash' => $this->contextHash,
            'blueprint_reference' => $this->blueprintReference->toArray(),
            'template_reference' => $this->templateReference->toArray(),
            'status' => $this->status,
            'package_hash' => $this->packageHash,
            'created_at_utc' => $this->createdAtUtc,
            'sealed_at_utc' => $this->sealedAtUtc,
        );
    }
}
