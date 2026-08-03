<?php

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\ConnectorType;
use App\Infrastructure\Persistence\Models\ProjectConnector;
use Illuminate\Support\Collection;

class ConnectorRegistryService
{
    /** @var Collection<string, array<string, mixed>> */
    private Collection $inMemoryTypes;

    public function __construct()
    {
        $this->inMemoryTypes = collect();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function registerType(
        string $type,
        string $name,
        ?string $description = null,
        ?array $metadata = null,
    ): ConnectorType {
        $this->inMemoryTypes->put($type, compact('type', 'name', 'description', 'metadata'));

        return ConnectorType::updateOrCreate(
            ['type' => $type],
            [
                'name' => $name,
                'description' => $description,
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * @return Collection<int, ConnectorType>
     */
    public function listTypes(): Collection
    {
        $dbTypes = ConnectorType::query()->orderBy('name')->get();

        foreach ($this->inMemoryTypes as $type => $data) {
            if (! $dbTypes->contains('type', $type)) {
                $dbTypes->push(new ConnectorType($data));
            }
        }

        return $dbTypes->sortBy('name')->values();
    }

    public function findTypeByType(string $type): ?ConnectorType
    {
        return ConnectorType::query()->where('type', $type)->first()
            ?? ($this->inMemoryTypes->has($type) ? new ConnectorType($this->inMemoryTypes->get($type)) : null);
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function attachToProject(
        string $organizationId,
        string $projectId,
        string $connectorTypeId,
        string $name,
        ?array $config = null,
    ): ProjectConnector {
        return ProjectConnector::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'connector_type_id' => $connectorTypeId,
            'name' => $name,
            'config' => $config,
            'enabled' => true,
        ]);
    }

    /**
     * @return Collection<int, ProjectConnector>
     */
    public function listProjectConnectors(string $projectId): Collection
    {
        return ProjectConnector::query()
            ->where('project_id', $projectId)
            ->with('connectorType')
            ->orderBy('name')
            ->get();
    }
}
