<?php

namespace App\Modules\Announcement\Infrastructure\Persistence\Repositories;

use App\Modules\Announcement\Domain\AnnouncementRepositoryInterface;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class EloquentAnnouncementRepository implements AnnouncementRepositoryInterface
{
    private const CONTENT_COLUMNS = [
        'source_guid',
        'canonical_url',
        'source_published_at',
        'raw_title',
        'content_hash',
        'raw_payload',
        'last_seen_at',
    ];

    private string $lastInsertId = '';

    public function insert(array $data): bool
    {
        if (
            trim((string) ($data['organization_id'] ?? '')) === ''
            || trim((string) ($data['project_id'] ?? '')) === ''
            || trim((string) ($data['source_id'] ?? '')) === ''
        ) {
            return false;
        }

        try {
            $announcement = new Announcement;
            $announcement->fill([
                'organization_id' => $data['organization_id'],
                'project_id' => $data['project_id'],
                'source_id' => $data['source_id'],
                'identity_hash' => $data['identity_hash'] ?? null,
                'identity_basis' => $data['identity_basis'] ?? null,
                'source_guid' => $data['source_guid'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'source_published_at' => $data['source_published_at']
                    ?? $data['source_published_at_utc']
                    ?? null,
                'raw_title' => $data['raw_title'] ?? null,
                'content_hash' => $data['content_hash'] ?? null,
                'raw_payload' => $this->normalizeJsonArray($data['raw_payload'] ?? []),
                'revision_no' => $data['revision_no'] ?? 1,
                'first_seen_at' => $data['first_seen_at']
                    ?? $data['first_seen_at_utc']
                    ?? null,
                'last_seen_at' => $data['last_seen_at']
                    ?? $data['last_seen_at_utc']
                    ?? null,
            ]);

            if (array_key_exists('created_at_utc', $data)) {
                $announcement->created_at = $data['created_at_utc'];
            }

            if (array_key_exists('updated_at_utc', $data)) {
                $announcement->updated_at = $data['updated_at_utc'];
            }

            $announcement->id = (string) Str::uuid();
            $announcement->created_at ??= now();
            $announcement->updated_at ??= now();
            $inserted = Announcement::query()->insertOrIgnore($announcement->getAttributes());

            if ($inserted !== 1) {
                return false;
            }

            $this->lastInsertId = (string) $announcement->getKey();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function findBySourceAndIdentityHash(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $identityHash,
    ): ?array {
        $announcement = Announcement::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('source_id', $sourceId)
            ->where('identity_hash', $identityHash)
            ->lockForUpdate()
            ->first();

        return $announcement?->toArray();
    }

    public function lastInsertId(): string
    {
        return $this->lastInsertId;
    }

    public function markUnchanged(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $itemId,
        string $lastSeenAtUtc,
        string $updatedAtUtc,
    ): bool {
        if (trim($itemId) === '') {
            return false;
        }

        try {
            $announcement = Announcement::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('source_id', $sourceId)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if ($announcement === null) {
                return false;
            }

            $announcement->last_seen_at = $lastSeenAtUtc;
            $announcement->updated_at = $updatedAtUtc;

            return $announcement->save();
        } catch (Throwable) {
            return false;
        }
    }

    public function applyContentUpdate(
        string $organizationId,
        string $projectId,
        string $sourceId,
        string $itemId,
        array $data,
    ): int|false {
        if (trim($itemId) === '' || $data === []) {
            return false;
        }

        try {
            $query = Announcement::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->where('source_id', $sourceId)
                ->whereKey($itemId);
            $announcement = (clone $query)->lockForUpdate()->first();

            if ($announcement === null) {
                return false;
            }

            $updates = $this->contentData($data);

            if ($updates === [] && ! array_key_exists('updated_at_utc', $data)) {
                return false;
            }

            if (array_key_exists('raw_payload', $updates)) {
                $encoded = json_encode($updates['raw_payload']);
                $updates['raw_payload'] = is_string($encoded) ? $encoded : '{}';
            }

            $updates['revision_no'] = DB::raw('revision_no + 1');
            $updates['updated_at'] = $data['updated_at_utc'] ?? now();

            if ((clone $query)->update($updates) !== 1) {
                return false;
            }

            return (int) (clone $query)->value('revision_no');
        } catch (Throwable) {
            return false;
        }
    }

    public function findPage(
        string $organizationId,
        string $projectId,
        array $criteria,
    ): array {
        $page = max(1, (int) ($criteria['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($criteria['per_page'] ?? 25)));
        $query = Announcement::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId);
        $search = trim((string) ($criteria['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';
            $query->where(function ($query) use ($like): void {
                $query
                    ->whereRaw('LOWER(raw_title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(canonical_url) LIKE ?', [$like]);
            });
        }

        $sourceId = trim((string) ($criteria['source_id'] ?? ''));

        if ($sourceId !== '') {
            $query->where('source_id', $sourceId);
        }

        $status = strtoupper(trim((string) ($criteria['status'] ?? '')));

        if ($status === 'NEW') {
            $query->where('revision_no', 1);
        } elseif ($status === 'UPDATED') {
            $query->where('revision_no', '>', 1);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static function (Announcement $announcement): array {
                $item = $announcement->toArray();
                $item['status'] = $announcement->revision_no > 1 ? 'UPDATED' : 'NEW';

                return $item;
            })
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function findById(
        string $organizationId,
        string $projectId,
        string $itemId,
    ): array {
        $announcement = Announcement::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->whereKey($itemId)
            ->first();

        return $announcement?->toArray() ?? [];
    }

    public function findEditorialSummary(
        string $organizationId,
        string $projectId,
    ): array {
        $query = Announcement::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId);
        $total = (clone $query)->count();
        $newCount = (clone $query)->where('revision_no', 1)->count();
        $updatedCount = (clone $query)->where('revision_no', '>', 1)->count();

        return [
            'total' => $total,
            'new_count' => $newCount,
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contentData(array $data): array
    {
        if (array_key_exists('source_published_at_utc', $data)) {
            $data['source_published_at'] = $data['source_published_at_utc'];
        }

        if (array_key_exists('last_seen_at_utc', $data)) {
            $data['last_seen_at'] = $data['last_seen_at_utc'];
        }

        $updates = [];

        foreach (self::CONTENT_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $updates[$column] = $data[$column];
            }
        }

        if (array_key_exists('raw_payload', $updates)) {
            $updates['raw_payload'] = $this->normalizeJsonArray($updates['raw_payload']);
        }

        return $updates;
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
