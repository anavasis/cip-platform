<?php

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\Secret;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Collection;

class SecretService
{
    public function __construct(
        private readonly SecretEncryptionService $encryption,
        private readonly AuditService $audit,
    ) {}

    public function create(
        string $organizationId,
        string $key,
        string $plaintext,
        ?string $projectId = null,
        ?User $user = null,
    ): Secret {
        $secret = Secret::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'key' => $key,
            'encrypted_value' => $this->encryption->encrypt($plaintext),
        ]);

        $this->audit->record('secret.created', $user, $organizationId, $projectId, 'secret', $secret->id, [
            'key' => $key,
        ]);

        return $secret;
    }

    public function update(Secret $secret, string $plaintext, ?User $user = null): Secret
    {
        $secret->update([
            'encrypted_value' => $this->encryption->encrypt($plaintext),
        ]);

        $this->audit->record('secret.updated', $user, $secret->organization_id, $secret->project_id, 'secret', $secret->id);

        return $secret->fresh();
    }

    public function delete(Secret $secret, ?User $user = null): void
    {
        $this->audit->record('secret.deleted', $user, $secret->organization_id, $secret->project_id, 'secret', $secret->id);
        $secret->delete();
    }

    public function reveal(Secret $secret, ?User $user = null): string
    {
        $this->audit->record('secret.revealed', $user, $secret->organization_id, $secret->project_id, 'secret', $secret->id);

        return $this->encryption->decrypt($secret->encrypted_value);
    }

  /**
   * @return Collection<int, Secret>
   */
    public function list(string $organizationId, ?string $projectId = null): Collection
    {
        return Secret::query()
            ->where('organization_id', $organizationId)
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(! $projectId, fn ($q) => $q->whereNull('project_id'))
            ->orderBy('key')
            ->get();
    }

    public function maskValue(): string
    {
        return $this->encryption->mask();
    }
}
