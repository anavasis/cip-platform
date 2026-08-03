<?php

namespace App\Modules\Editorial\Application;

use App\Modules\Editorial\Domain\Contracts\GenerationDiagnosticsSink;

/**
 * Tenant-partitioned Editorial diagnostics (no bodies/secrets).
 */
final class EditorialDiagnostics implements GenerationDiagnosticsSink
{
    /** @var array<string, array<string, mixed>> */
    private array $byTenant = [];

    public function recordLastGeneration(array $payload): void
    {
        $organizationId = trim((string) ($payload['organization_id'] ?? ''));
        $projectId = trim((string) ($payload['project_id'] ?? ''));
        $key = $this->key($organizationId, $projectId);
        $current = $this->byTenant[$key] ?? $this->emptyState($organizationId, $projectId);

        $ok = ($payload['ok'] ?? false) === true;
        $current['generations_requested'] = (int) $current['generations_requested'] + 1;
        if ($ok) {
            $current['generations_completed'] = (int) $current['generations_completed'] + 1;
            $current['preview_available'] = (bool) ($payload['preview_available'] ?? true);
        } else {
            $current['generations_failed'] = (int) $current['generations_failed'] + 1;
            $current['preview_available'] = false;
            if (isset($payload['validation_failure_codes']) && is_array($payload['validation_failure_codes'])) {
                $current['validation_failure_codes'] = array_values(array_filter(
                    array_map('strval', $payload['validation_failure_codes'])
                ));
            } elseif (isset($payload['error']) && is_string($payload['error'])) {
                $current['validation_failure_codes'] = [$payload['error']];
            }
        }

        if (isset($payload['provider_code'])) {
            $current['last_provider_code'] = (string) $payload['provider_code'];
        }
        if (isset($payload['model_id'])) {
            $current['last_model_id'] = (string) $payload['model_id'];
        }
        if (isset($payload['duration_ms'])) {
            $current['last_duration_ms'] = (int) $payload['duration_ms'];
        }
        if (isset($payload['result_status'])) {
            $current['latest_result_status'] = (string) $payload['result_status'];
        }
        if (isset($payload['correlation_id'])) {
            $current['last_correlation_id'] = (string) $payload['correlation_id'];
        }
        if (isset($payload['retry_count'])) {
            $current['retry_count'] = (int) $payload['retry_count'];
        }

        // Strip bodies if accidentally passed
        unset($payload['body'], $payload['content_text'], $payload['prompt'], $payload['preview_body']);
        $current['last_generation'] = $payload;
        $this->byTenant[$key] = $current;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $organizationId, string $projectId): array
    {
        $key = $this->key($organizationId, $projectId);

        return $this->byTenant[$key] ?? $this->emptyState($organizationId, $projectId);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(string $organizationId, string $projectId): array
    {
        return [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'generations_requested' => 0,
            'generations_completed' => 0,
            'generations_failed' => 0,
            'last_provider_code' => null,
            'last_model_id' => null,
            'last_duration_ms' => null,
            'latest_result_status' => null,
            'preview_available' => false,
            'validation_failure_codes' => [],
            'retry_count' => 0,
            'last_correlation_id' => null,
            'last_generation' => null,
        ];
    }

    private function key(string $organizationId, string $projectId): string
    {
        return $organizationId.'|'.$projectId;
    }
}
