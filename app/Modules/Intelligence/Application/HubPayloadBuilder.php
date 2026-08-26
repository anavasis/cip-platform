<?php

namespace App\Modules\Intelligence\Application;

use App\Application\Services\ConfigurationService;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Carbon;

/**
 * Read-only Hub payload builder (no DB writes, no remote calls).
 */
final class HubPayloadBuilder
{
    public const HUB_PROFILE_KEY = 'editorial.hub_profile';

    private const DEFAULT_STALE_THRESHOLD_HOURS = 168;

    public function __construct(
        private readonly ConfigurationService $configuration,
        private readonly EntityLifecycleService $lifecycleService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $organizationId, string $projectId, ?\DateTimeInterface $now = null): array
    {
        $now = $now !== null ? Carbon::instance($now) : now();
        $hubProfile = $this->loadHubProfile($organizationId, $projectId);
        $staleThresholdHours = (int) ($hubProfile['stale_threshold_hours'] ?? self::DEFAULT_STALE_THRESHOLD_HOURS);

        $entities = ContentEntityModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('hub_member', true)
            ->where('publish_eligible', true)
            ->where('archive_state', 'active')
            ->orderBy('entity_id')
            ->get();

        $records = [];
        $oldestVerifiedAt = null;

        foreach ($entities as $entity) {
            $evaluation = $this->lifecycleService->evaluate($entity, $now, $staleThresholdHours);

            if ($evaluation['is_public_eligible'] !== true) {
                continue;
            }

            $satelliteBinding = RemotePostBindingModel::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('content_entity_id', $entity->id)
                ->where('remote_system', 'wordpress')
                ->whereNotNull('confirmed_at')
                ->where('canonical_url', '!=', '')
                ->first();

            if ($satelliteBinding === null) {
                continue;
            }

            $satelliteUrl = trim((string) $satelliteBinding->canonical_url);
            if ($satelliteUrl === '') {
                continue;
            }

            $lastVerifiedAt = $entity->last_verified_at?->toIso8601String();
            if ($lastVerifiedAt !== null) {
                if ($oldestVerifiedAt === null || $lastVerifiedAt < $oldestVerifiedAt) {
                    $oldestVerifiedAt = $lastVerifiedAt;
                }
            }

            $records[] = [
                'entity_id' => (string) $entity->entity_id,
                'title' => (string) $entity->label,
                'code' => $entity->code,
                'organization' => $entity->organization_body,
                'source_family' => (string) $entity->source_family,
                'thematic_categories' => is_array($entity->thematic_categories)
                    ? array_values($entity->thematic_categories)
                    : [],
                'lifecycle_status' => $evaluation['effective_lifecycle_status'],
                'display_section' => $evaluation['display_section'],
                'positions_count' => $entity->positions_count,
                'application_open_at' => $entity->application_open_at?->toIso8601String(),
                'application_deadline_at' => $entity->application_deadline_at?->toIso8601String(),
                'next_step_label' => $entity->next_step_label,
                'satellite_url' => $satelliteUrl,
                'last_verified_at' => $lastVerifiedAt,
            ];
        }

        usort(
            $records,
            static fn (array $a, array $b): int => strcmp((string) $a['entity_id'], (string) $b['entity_id']),
        );

        return [
            'schema_version' => 1,
            'hub' => [
                'entity_id' => (string) ($hubProfile['hub_entity_id'] ?? 'hub'),
                'url' => $hubProfile['hub_url'] ?? null,
                'title' => $hubProfile['hub_title'] ?? null,
            ],
            'generated_at' => $now->toIso8601String(),
            'freshness' => [
                'oldest_verified_at' => $oldestVerifiedAt,
                'stale_threshold_hours' => $staleThresholdHours,
            ],
            'records' => $records,
            'filters' => $this->buildFilters($records),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array<int, string>>
     */
    private function buildFilters(array $records): array
    {
        $lifecycle = [];
        $sourceFamily = [];
        $thematic = [];

        foreach ($records as $record) {
            $section = isset($record['display_section']) ? (string) $record['display_section'] : '';
            if ($section !== '') {
                $lifecycle[$section] = true;
            }

            $family = isset($record['source_family']) ? (string) $record['source_family'] : '';
            if ($family !== '') {
                $sourceFamily[$family] = true;
            }

            $categories = isset($record['thematic_categories']) && is_array($record['thematic_categories'])
                ? $record['thematic_categories']
                : [];

            foreach ($categories as $category) {
                $category = (string) $category;
                if ($category !== '') {
                    $thematic[$category] = true;
                }
            }
        }

        $lifecycleKeys = array_keys($lifecycle);
        $sourceFamilyKeys = array_keys($sourceFamily);
        $thematicKeys = array_keys($thematic);
        sort($lifecycleKeys);
        sort($sourceFamilyKeys);
        sort($thematicKeys);

        return [
            'lifecycle' => $lifecycleKeys,
            'source_family' => $sourceFamilyKeys,
            'thematic' => $thematicKeys,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadHubProfile(string $organizationId, string $projectId): array
    {
        $entry = $this->configuration->get($organizationId, self::HUB_PROFILE_KEY, $projectId);
        if ($entry === null) {
            return [
                'hub_entity_id' => 'hub',
                'hub_url' => null,
                'hub_title' => null,
                'stale_threshold_hours' => self::DEFAULT_STALE_THRESHOLD_HOURS,
            ];
        }

        $value = $entry->value;
        $profile = is_array($value) && isset($value['value']) && is_array($value['value'])
            ? $value['value']
            : (is_array($value) ? $value : []);

        return [
            'hub_entity_id' => isset($profile['hub_entity_id']) ? (string) $profile['hub_entity_id'] : 'hub',
            'hub_url' => isset($profile['hub_url']) ? (string) $profile['hub_url'] : null,
            'hub_title' => isset($profile['hub_title']) ? (string) $profile['hub_title'] : null,
            'stale_threshold_hours' => isset($profile['stale_threshold_hours'])
                ? (int) $profile['stale_threshold_hours']
                : self::DEFAULT_STALE_THRESHOLD_HOURS,
        ];
    }
}
