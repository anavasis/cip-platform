<?php

namespace App\Modules\Intelligence\Domain;

/**
 * Immutable read-only Content Intelligence plan (plan preview only — no persistence).
 */
final class ContentIntelligencePlan
{
    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_AMBIGUOUS = 'ambiguous';

    public const STATUS_INVALID_PROFILE = 'invalid_profile';

    public const ACTION_UPDATE_EXISTING = 'update_existing';

    public const ACTION_CREATE_NEW = 'create_new';

    public const ACTION_NO_PUBLISH = 'no_publish';

    public const HUB_IMPACT_NONE = 'none';

    public const HUB_IMPACT_UPDATE_REQUIRED = 'update_required';

    public const HUB_IMPACT_SELF_UPDATE = 'self_update';

    public const MATCH_LOCATION_TITLE = 'title';

    public const MATCH_LOCATION_BODY = 'body';

    private string $status;

    private string $confidence;

    private ?string $entityId;

    private ?string $entityLabel;

    private ?string $matchedPattern;

    private ?string $contentRole;

    private string $action;

    private ?string $canonicalTargetUrl;

    private ?string $parentHubEntityId;

    private ?string $parentHubLabel;

    private ?string $parentHubUrl;

    private string $hubImpact;

    /** @var array<string, mixed>|null */
    private ?array $seoPlan;

    /** @var array<int, array<string, mixed>> */
    private array $internalLinks;

    /** @var array<int, array<string, mixed>> */
    private array $publishingOperations;

    /** @var array<int, string> */
    private array $warnings;

    private ?string $matchLocation;

    private bool $primaryBindingEligible;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->status = (string) ($data['status'] ?? self::STATUS_UNRESOLVED);
        $this->confidence = (string) ($data['confidence'] ?? 'none');
        $this->entityId = isset($data['entity_id']) ? (string) $data['entity_id'] : null;
        $this->entityLabel = isset($data['entity_label']) ? (string) $data['entity_label'] : null;
        $this->matchedPattern = isset($data['matched_pattern']) ? (string) $data['matched_pattern'] : null;
        $this->contentRole = isset($data['content_role']) ? (string) $data['content_role'] : null;
        $this->action = (string) ($data['action'] ?? self::ACTION_NO_PUBLISH);
        $this->canonicalTargetUrl = isset($data['canonical_target_url']) ? (string) $data['canonical_target_url'] : null;
        $this->parentHubEntityId = isset($data['parent_hub_entity_id']) ? (string) $data['parent_hub_entity_id'] : null;
        $this->parentHubLabel = isset($data['parent_hub_label']) ? (string) $data['parent_hub_label'] : null;
        $this->parentHubUrl = isset($data['parent_hub_url']) ? (string) $data['parent_hub_url'] : null;
        $this->hubImpact = (string) ($data['hub_impact'] ?? self::HUB_IMPACT_NONE);
        $this->seoPlan = isset($data['seo_plan']) && is_array($data['seo_plan']) ? $data['seo_plan'] : null;
        $this->internalLinks = is_array($data['internal_links'] ?? null) ? $data['internal_links'] : [];
        $this->publishingOperations = is_array($data['publishing_operations'] ?? null) ? $data['publishing_operations'] : [];
        $this->warnings = is_array($data['warnings'] ?? null) ? array_values(array_map('strval', $data['warnings'])) : [];
        $matchLocation = isset($data['match_location']) ? (string) $data['match_location'] : null;
        $this->matchLocation = in_array($matchLocation, [self::MATCH_LOCATION_TITLE, self::MATCH_LOCATION_BODY], true)
            ? $matchLocation
            : null;
        $this->primaryBindingEligible = ($data['primary_binding_eligible'] ?? false) === true;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function confidence(): string
    {
        return $this->confidence;
    }

    public function entityId(): ?string
    {
        return $this->entityId;
    }

    public function entityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function matchedPattern(): ?string
    {
        return $this->matchedPattern;
    }

    public function contentRole(): ?string
    {
        return $this->contentRole;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function canonicalTargetUrl(): ?string
    {
        return $this->canonicalTargetUrl;
    }

    public function parentHubEntityId(): ?string
    {
        return $this->parentHubEntityId;
    }

    public function parentHubLabel(): ?string
    {
        return $this->parentHubLabel;
    }

    public function parentHubUrl(): ?string
    {
        return $this->parentHubUrl;
    }

    public function hubImpact(): string
    {
        return $this->hubImpact;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function seoPlan(): ?array
    {
        return $this->seoPlan;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function internalLinks(): array
    {
        return $this->internalLinks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishingOperations(): array
    {
        return $this->publishingOperations;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function matchLocation(): ?string
    {
        return $this->matchLocation;
    }

    public function primaryBindingEligible(): bool
    {
        return $this->primaryBindingEligible;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'confidence' => $this->confidence,
            'entity_id' => $this->entityId,
            'entity_label' => $this->entityLabel,
            'matched_pattern' => $this->matchedPattern,
            'content_role' => $this->contentRole,
            'action' => $this->action,
            'canonical_target_url' => $this->canonicalTargetUrl,
            'parent_hub_entity_id' => $this->parentHubEntityId,
            'parent_hub_label' => $this->parentHubLabel,
            'parent_hub_url' => $this->parentHubUrl,
            'hub_impact' => $this->hubImpact,
            'seo_plan' => $this->seoPlan,
            'internal_links' => $this->internalLinks,
            'publishing_operations' => $this->publishingOperations,
            'warnings' => $this->warnings,
            'match_location' => $this->matchLocation,
            'primary_binding_eligible' => $this->primaryBindingEligible,
        ];
    }
}
