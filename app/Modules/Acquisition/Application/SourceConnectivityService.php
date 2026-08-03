<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;

/**
 * Performs a tenant-scoped acquisition as a source connectivity check.
 */
final readonly class SourceConnectivityService
{
    public function __construct(
        private SourceRepositoryInterface $repository,
        private SourceAcquisitionService $acquisition,
    ) {}

    /** @return array<string, mixed> */
    public function check(string $organizationId, string $projectId, string $sourceId): array
    {
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);
        $sourceId = trim($sourceId);

        if ($organizationId === '' || $projectId === '' || $sourceId === '') {
            return $this->failure('invalid_request');
        }

        if ($this->repository->findById($organizationId, $projectId, $sourceId) === null) {
            return $this->failure('not_found');
        }

        $result = $this->acquisition->acquireFromSource($organizationId, $projectId, $sourceId);
        $fetch = $result->fetchResult();
        $success = $result->success();
        $errorCode = $success ? '' : ($result->errorCode() !== '' ? $result->errorCode() : 'check_failed');
        $checkedAt = gmdate('Y-m-d H:i:s');
        $status = $success ? 'success' : $errorCode;
        $statusPersisted = $this->repository->update($sourceId, [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'last_checked_at' => $checkedAt,
            'last_check_status' => $status,
        ]);

        return [
            'success' => $success,
            'error_code' => $errorCode,
            'http_status' => isset($fetch['http_status']) ? (int) $fetch['http_status'] : 0,
            'content_type' => isset($fetch['content_type']) ? (string) $fetch['content_type'] : '',
            'response_size' => isset($fetch['response_size']) ? (int) $fetch['response_size'] : 0,
            'duration_ms' => $result->duration(),
            'checked_at' => $checkedAt,
            'status_persisted' => $statusPersisted,
        ];
    }

    /** @return array<string, mixed> */
    private function failure(string $errorCode): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'http_status' => 0,
            'content_type' => '',
            'response_size' => 0,
            'duration_ms' => 0.0,
            'checked_at' => '',
            'status_persisted' => false,
        ];
    }
}
