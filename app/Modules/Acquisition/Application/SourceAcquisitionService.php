<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionResult;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;

/**
 * Tenant-scoped source-to-acquisition entry point.
 */
final readonly class SourceAcquisitionService
{
    public function __construct(
        private SourceRepositoryInterface $repository,
        private AcquisitionEngine $acquisitionEngine,
    ) {}

    public function acquireFromSource(
        string $organizationId,
        string $projectId,
        string $sourceId,
        array $context = [],
    ): AcquisitionResult {
        $organizationId = trim($organizationId);
        $projectId = trim($projectId);
        $sourceId = trim($sourceId);

        if ($organizationId === '' || $projectId === '') {
            return $this->preAcquireError('invalid_tenant');
        }

        if ($sourceId === '') {
            return $this->preAcquireError('invalid_id');
        }

        $source = $this->repository->findById($organizationId, $projectId, $sourceId);

        if ($source === null) {
            return $this->preAcquireError('not_found');
        }

        return $this->acquire($this->buildRequestFromSource(
            $source,
            $organizationId,
            $projectId,
            $sourceId,
            $context,
        ));
    }

    /** @param array<string, mixed> $request */
    public function acquire(array $request): AcquisitionResult
    {
        return $this->acquisitionEngine->acquire($request);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function buildRequestFromSource(
        array $source,
        string $organizationId,
        string $projectId,
        string $sourceId,
        array $context,
    ): array {
        return [
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'source_key' => isset($source['slug']) ? (string) $source['slug'] : $sourceId,
            'url' => isset($source['feed_url']) ? trim((string) $source['feed_url']) : '',
            'allowed_domains' => $this->decodeAllowedDomains($source['allowed_domains'] ?? null),
            'source_type' => isset($source['source_type'])
                ? strtolower(trim((string) $source['source_type']))
                : '',
            'parser_profile' => isset($source['parser_profile'])
                ? trim((string) $source['parser_profile'])
                : '',
            'correlation_id' => isset($context['correlation_id'])
                ? trim((string) $context['correlation_id'])
                : '',
            'run_id' => isset($context['run_id'])
                ? trim((string) $context['run_id'])
                : '',
        ];
    }

    /** @return array<int, string> */
    private function decodeAllowedDomains(mixed $value): array
    {
        $decoded = is_array($value)
            ? $value
            : (is_string($value) && $value !== '' ? json_decode($value, true) : []);

        if (! is_array($decoded)) {
            return [];
        }

        $domains = [];

        foreach ($decoded as $domain) {
            if (is_string($domain) && trim($domain) !== '') {
                $domains[] = strtolower(trim($domain));
            }
        }

        return array_values(array_unique($domains));
    }

    private function preAcquireError(string $errorCode): AcquisitionResult
    {
        return new AcquisitionResult([
            'success' => false,
            'warnings' => [],
            'errors' => [$errorCode],
            'error_code' => $errorCode,
        ]);
    }
}
