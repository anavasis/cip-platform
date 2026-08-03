<?php

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\ConfigurationEntry;
use Illuminate\Support\Collection;

class ConfigurationService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public function set(
        string $organizationId,
        string $key,
        array $value,
        ?string $projectId = null,
        ?\App\Infrastructure\Persistence\Models\User $user = null,
    ): ConfigurationEntry {
        $entry = ConfigurationEntry::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'key' => $key,
            ],
            ['value' => $value]
        );

        $this->audit->record('config.set', $user, $organizationId, $projectId, 'config', $entry->id, [
            'key' => $key,
        ]);

        return $entry;
    }

    public function get(
        string $organizationId,
        string $key,
        ?string $projectId = null,
    ): ?ConfigurationEntry {
        return ConfigurationEntry::query()
            ->where('organization_id', $organizationId)
            ->where('key', $key)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(! $projectId, fn ($q) => $q->whereNull('project_id'))
            ->first();
    }

  /**
   * @return Collection<int, ConfigurationEntry>
   */
    public function list(string $organizationId, ?string $projectId = null): Collection
    {
        return ConfigurationEntry::query()
            ->where('organization_id', $organizationId)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(! $projectId, fn ($q) => $q->whereNull('project_id'))
            ->orderBy('key')
            ->get();
    }
}
