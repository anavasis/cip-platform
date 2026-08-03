<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Repositories;

use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Throwable;

final class EloquentSourceRepository implements SourceRepositoryInterface
{
    private const WRITABLE_COLUMNS = [
        'slug',
        'name',
        'source_type',
        'base_url',
        'feed_url',
        'feed_url_hash',
        'allowed_domains',
        'parser_profile',
        'enabled',
        'manual_only',
        'last_acquired_at',
        'last_checked_at',
        'last_check_status',
    ];

    public function findAll(string $organizationId, string $projectId): array
    {
        return Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->orderBy('slug')
            ->get()
            ->map(static fn (Source $source): array => $source->toArray())
            ->all();
    }

    public function findById(string $organizationId, string $projectId, string $id): ?array
    {
        $source = Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->whereKey($id)
            ->first();

        return $source?->toArray();
    }

    public function findDue(string $organizationId, string $projectId): array
    {
        return Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('enabled', true)
            ->where('manual_only', false)
            ->orderBy('last_checked_at')
            ->orderBy('slug')
            ->get()
            ->map(static fn (Source $source): array => $source->toArray())
            ->all();
    }

    public function slugExists(
        string $organizationId,
        string $projectId,
        string $slug,
        ?string $excludeId = null,
    ): bool {
        $query = Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('slug', $slug);

        if ($excludeId !== null && trim($excludeId) !== '') {
            $query->whereKeyNot($excludeId);
        }

        return $query->exists();
    }

    public function feedHashExists(
        string $organizationId,
        string $projectId,
        string $hash,
        ?string $excludeId = null,
    ): bool {
        $query = Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('feed_url_hash', $hash);

        if ($excludeId !== null && trim($excludeId) !== '') {
            $query->whereKeyNot($excludeId);
        }

        return $query->exists();
    }

    public function insert(array $data): bool|string
    {
        if (
            trim((string) ($data['organization_id'] ?? '')) === ''
            || trim((string) ($data['project_id'] ?? '')) === ''
        ) {
            return false;
        }

        try {
            $source = new Source;
            $source->fill([
                'organization_id' => $data['organization_id'],
                'project_id' => $data['project_id'],
                ...$this->writableData($data),
            ]);
            $this->applyLegacyTimestamps($source, $data);
            $source->save();

            return (string) $source->getKey();
        } catch (Throwable) {
            return false;
        }
    }

    public function update(string $id, array $data): bool
    {
        if (trim($id) === '' || $data === []) {
            return false;
        }

        $hasOrganization = array_key_exists('organization_id', $data);
        $hasProject = array_key_exists('project_id', $data);

        if ($hasOrganization !== $hasProject) {
            return false;
        }

        try {
            $query = Source::query()->whereKey($id);

            if ($hasOrganization && $hasProject) {
                $query
                    ->where('organization_id', (string) $data['organization_id'])
                    ->where('project_id', (string) $data['project_id']);
            }

            $source = $query->first();
            $updates = $this->writableData($data);

            if ($source === null || ($updates === [] && ! array_key_exists('updated_at_utc', $data))) {
                return false;
            }

            $source->fill($updates);

            if (array_key_exists('updated_at_utc', $data)) {
                $source->updated_at = $data['updated_at_utc'];
            }

            return $source->save();
        } catch (Throwable) {
            return false;
        }
    }

    public function setEnabled(
        string $organizationId,
        string $projectId,
        string $id,
        bool $enabled,
    ): bool {
        try {
            $source = Source::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->whereKey($id)
                ->first();

            if ($source === null) {
                return false;
            }

            $source->enabled = $enabled;

            return $source->save();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function writableData(array $data): array
    {
        $writable = [];

        foreach (self::WRITABLE_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $writable[$column] = $data[$column];
            }
        }

        if (array_key_exists('base_url', $writable) && $writable['base_url'] === null) {
            $writable['base_url'] = '';
        }

        if (array_key_exists('allowed_domains', $writable)) {
            $writable['allowed_domains'] = $this->normalizeJsonArray($writable['allowed_domains']);
        }

        return $writable;
    }

    /** @param array<string, mixed> $data */
    private function applyLegacyTimestamps(Source $source, array $data): void
    {
        if (array_key_exists('created_at_utc', $data)) {
            $source->created_at = $data['created_at_utc'];
        }

        if (array_key_exists('updated_at_utc', $data)) {
            $source->updated_at = $data['updated_at_utc'];
        }
    }

    /** @return array<int|string, mixed> */
    private function normalizeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
