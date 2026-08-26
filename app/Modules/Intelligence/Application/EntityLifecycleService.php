<?php

namespace App\Modules\Intelligence\Application;

use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;

/**
 * Pure deterministic lifecycle and public-eligibility evaluation (no DB writes).
 */
final class EntityLifecycleService
{
    public const LIFECYCLE_OPEN = 'open';

    public const LIFECYCLE_VERIFICATION_REQUIRED = 'verification_required';

    public const LIFECYCLE_IN_PROGRESS = 'in_progress';

    public const LIFECYCLE_RESULTS = 'results';

    public const LIFECYCLE_OBJECTIONS = 'objections';

    public const LIFECYCLE_FINAL_RESULTS = 'final_results';

    public const LIFECYCLE_COMPLETED = 'completed';

    public const LIFECYCLE_ARCHIVED = 'archived';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_VERIFICATION_REQUIRED = 'verification_required';

    public const VERIFICATION_STALE = 'stale';

    public const VERIFICATION_UNVERIFIABLE = 'unverifiable';

    /**
     * @return array{
     *     effective_lifecycle_status: string,
     *     effective_verification_status: string,
     *     is_public_eligible: bool,
     *     display_section: string|null
     * }
     */
    public function evaluate(
        ContentEntityModel $entity,
        \DateTimeInterface $now,
        int $staleThresholdHours,
    ): array {
        $effectiveLifecycle = (string) $entity->lifecycle_status;
        $effectiveVerification = (string) $entity->verification_status;

        if (
            $effectiveLifecycle === self::LIFECYCLE_OPEN
            && $entity->application_deadline_at !== null
            && $entity->application_deadline_at->lt($now)
        ) {
            $effectiveLifecycle = self::LIFECYCLE_VERIFICATION_REQUIRED;
        }

        if ($this->isStale($entity, $now, $staleThresholdHours)) {
            $effectiveVerification = self::VERIFICATION_STALE;
        }

        $isPublicEligible = $this->isPublicEligible(
            $entity,
            $effectiveLifecycle,
            $effectiveVerification,
        );

        $displaySection = $isPublicEligible
            ? $this->resolveDisplaySection($entity, $effectiveLifecycle)
            : null;

        return [
            'effective_lifecycle_status' => $effectiveLifecycle,
            'effective_verification_status' => $effectiveVerification,
            'is_public_eligible' => $isPublicEligible,
            'display_section' => $displaySection,
        ];
    }

    private function isPublicEligible(
        ContentEntityModel $entity,
        string $effectiveLifecycle,
        string $effectiveVerification,
    ): bool {
        if ($effectiveVerification !== self::VERIFICATION_VERIFIED) {
            return false;
        }

        if ((string) $entity->archive_state !== 'active') {
            return false;
        }

        if ($entity->hub_member !== true) {
            return false;
        }

        if ($entity->publish_eligible !== true) {
            return false;
        }

        if (in_array($effectiveLifecycle, [
            self::LIFECYCLE_VERIFICATION_REQUIRED,
            self::LIFECYCLE_COMPLETED,
            self::LIFECYCLE_ARCHIVED,
        ], true)) {
            return false;
        }

        return in_array($effectiveLifecycle, [
            self::LIFECYCLE_OPEN,
            self::LIFECYCLE_IN_PROGRESS,
            self::LIFECYCLE_RESULTS,
            self::LIFECYCLE_OBJECTIONS,
            self::LIFECYCLE_FINAL_RESULTS,
        ], true);
    }

    private function isStale(
        ContentEntityModel $entity,
        \DateTimeInterface $now,
        int $staleThresholdHours,
    ): bool {
        if ($entity->last_verified_at === null) {
            return true;
        }

        $staleAfter = $entity->last_verified_at->copy()->addHours(max(1, $staleThresholdHours));

        return $staleAfter->lt($now);
    }

    private function resolveDisplaySection(
        ContentEntityModel $entity,
        string $effectiveLifecycle,
    ): ?string {
        $persisted = trim((string) ($entity->hub_display_section ?? ''));

        if ($persisted !== '' && $this->isDisplaySectionCompatible($persisted, $effectiveLifecycle)) {
            return $persisted;
        }

        return match ($effectiveLifecycle) {
            self::LIFECYCLE_OPEN => 'open_now',
            self::LIFECYCLE_IN_PROGRESS => 'in_progress',
            self::LIFECYCLE_RESULTS, self::LIFECYCLE_OBJECTIONS, self::LIFECYCLE_FINAL_RESULTS => 'results',
            default => null,
        };
    }

    private function isDisplaySectionCompatible(string $section, string $effectiveLifecycle): bool
    {
        return match ($section) {
            'open_now' => $effectiveLifecycle === self::LIFECYCLE_OPEN,
            'in_progress' => $effectiveLifecycle === self::LIFECYCLE_IN_PROGRESS,
            'results' => in_array($effectiveLifecycle, [
                self::LIFECYCLE_RESULTS,
                self::LIFECYCLE_OBJECTIONS,
                self::LIFECYCLE_FINAL_RESULTS,
            ], true),
            default => false,
        };
    }
}
