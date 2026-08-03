<?php

namespace App\Modules\Acquisition\Infrastructure\Persistence\Repositories;

use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRunItem;
use Throwable;

final class EloquentAcquisitionRunRepository
{
    private const RUN_UPDATE_COLUMNS = [
        'status',
        'error_code',
        'sources_requested',
        'sources_succeeded',
        'sources_failed',
        'duration_ms',
        'meta',
    ];

    private const ITEM_UPDATE_COLUMNS = [
        'source_id',
        'success',
        'error_code',
        'result_meta',
    ];

    /** @param array<string, mixed> $data */
    public function createRun(array $data): bool|string
    {
        if (
            trim((string) ($data['organization_id'] ?? '')) === ''
            || trim((string) ($data['project_id'] ?? '')) === ''
            || trim((string) ($data['run_id'] ?? '')) === ''
            || trim((string) ($data['status'] ?? '')) === ''
        ) {
            return false;
        }

        try {
            $run = new AcquisitionRun;
            $run->fill([
                'organization_id' => $data['organization_id'],
                'project_id' => $data['project_id'],
                'run_id' => $data['run_id'],
                'status' => $data['status'],
                'error_code' => $data['error_code'] ?? null,
                'sources_requested' => $data['sources_requested'] ?? 0,
                'sources_succeeded' => $data['sources_succeeded'] ?? 0,
                'sources_failed' => $data['sources_failed'] ?? 0,
                'duration_ms' => $data['duration_ms'] ?? null,
                'meta' => $this->normalizeNullableJsonArray($data['meta'] ?? null),
            ]);
            $run->save();

            return (string) $run->getKey();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The identifier may be either the internal UUID or the opaque run_id.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRun(string $identifier, array $data): bool
    {
        if (trim($identifier) === '' || $data === []) {
            return false;
        }

        try {
            $query = AcquisitionRun::query()
                ->where(function ($query) use ($identifier): void {
                    $query->whereKey($identifier)->orWhere('run_id', $identifier);
                });
            $this->scopeToTenantWhenPresent($query, $data);
            $run = $query->first();
            $updates = $this->onlyColumns($data, self::RUN_UPDATE_COLUMNS);

            if ($run === null || $updates === []) {
                return false;
            }

            if (array_key_exists('meta', $updates)) {
                $updates['meta'] = $this->normalizeNullableJsonArray($updates['meta']);
            }

            $run->fill($updates);

            return $run->save();
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function createItem(array $data): bool|string
    {
        $runId = trim((string) ($data['acquisition_run_id'] ?? ''));
        $organizationId = trim((string) ($data['organization_id'] ?? ''));
        $projectId = trim((string) ($data['project_id'] ?? ''));

        if (
            $runId === ''
            || $organizationId === ''
            || $projectId === ''
            || ! array_key_exists('success', $data)
        ) {
            return false;
        }

        try {
            $runExists = AcquisitionRun::query()
                ->whereKey($runId)
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->exists();

            if (! $runExists) {
                return false;
            }

            $item = new AcquisitionRunItem;
            $item->fill([
                'acquisition_run_id' => $runId,
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'source_id' => $data['source_id'] ?? null,
                'success' => (bool) $data['success'],
                'error_code' => $data['error_code'] ?? null,
                'result_meta' => $this->sanitizeResultMeta($data['result_meta'] ?? null),
            ]);
            $item->save();

            return (string) $item->getKey();
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function updateItem(string $id, array $data): bool
    {
        if (trim($id) === '' || $data === []) {
            return false;
        }

        try {
            $query = AcquisitionRunItem::query()->whereKey($id);
            $this->scopeToTenantWhenPresent($query, $data);
            $item = $query->first();
            $updates = $this->onlyColumns($data, self::ITEM_UPDATE_COLUMNS);

            if ($item === null || $updates === []) {
                return false;
            }

            if (array_key_exists('result_meta', $updates)) {
                $updates['result_meta'] = $this->sanitizeResultMeta($updates['result_meta']);
            }

            $item->fill($updates);

            return $item->save();
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function findByRunId(
        string $organizationId,
        string $projectId,
        string $runId,
    ): ?array {
        $run = AcquisitionRun::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('run_id', $runId)
            ->first();

        return $run?->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function findItems(
        string $organizationId,
        string $projectId,
        string $acquisitionRunId,
    ): array {
        return AcquisitionRunItem::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('acquisition_run_id', $acquisitionRunId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (AcquisitionRunItem $item): array => $item->toArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function onlyColumns(array $data, array $columns): array
    {
        $result = [];

        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $result[$column] = $data[$column];
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $data */
    private function scopeToTenantWhenPresent(object $query, array $data): void
    {
        if (array_key_exists('organization_id', $data)) {
            $query->where('organization_id', (string) $data['organization_id']);
        }

        if (array_key_exists('project_id', $data)) {
            $query->where('project_id', (string) $data['project_id']);
        }
    }

    /** @return array<int|string, mixed>|null */
    private function normalizeNullableJsonArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int|string, mixed>|null */
    private function sanitizeResultMeta(mixed $value): ?array
    {
        $meta = $this->normalizeNullableJsonArray($value);

        if ($meta === null) {
            return null;
        }

        return $this->removeEvidenceBodies($meta);
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function removeEvidenceBodies(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, ['body', 'raw_body', 'evidence_body'], true)) {
                unset($value[$key]);

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->removeEvidenceBodies($item);
            }
        }

        return $value;
    }
}
