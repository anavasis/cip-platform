<?php

namespace App\Modules\Editorial\Domain\GenerationResult;

use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestStatus;


/**
 * Builds an immutable Generation Result from a Generation Request.
 * Provider-agnostic outcome only — no HTTP, providers, queues, or retries.
 */
final class GenerationResultBuilder
{
    /**
     * @param GenerationRequest $request
     * @param ProviderExecutionReference $execution
     * @param array<int, GeneratedArtifactReference|array<string, mixed>> $artifacts
     * @param array<string, mixed> $overrides
     * @return GenerationResult
     *
     * @throws \InvalidArgumentException
     */
    public function buildSuccessFromRequest(
        GenerationRequest $request,
        ProviderExecutionReference $execution,
        array $artifacts,
        array $overrides = array()
    ) {
        $mappedArtifacts = $this->normalizeArtifacts($artifacts);
        if ($mappedArtifacts === array()) {
            throw new \InvalidArgumentException('artifacts_required');
        }

        return $this->build(
            $request,
            $execution,
            GenerationResultStatus::SUCCESS,
            $mappedArtifacts,
            '',
            '',
            $overrides
        );
    }

    /**
     * @param GenerationRequest $request
     * @param ProviderExecutionReference $execution
     * @param string $errorCode
     * @param string $errorMessage
     * @param array<string, mixed> $overrides
     * @return GenerationResult
     *
     * @throws \InvalidArgumentException
     */
    public function buildErrorFromRequest(
        GenerationRequest $request,
        ProviderExecutionReference $execution,
        $errorCode,
        $errorMessage = '',
        array $overrides = array()
    ) {
        $normalizedCode = trim((string) $errorCode);
        if ($normalizedCode === '') {
            throw new \InvalidArgumentException('error_code_required');
        }

        return $this->build(
            $request,
            $execution,
            GenerationResultStatus::ERROR,
            array(),
            $normalizedCode,
            trim((string) $errorMessage),
            $overrides
        );
    }

    /**
     * @param GenerationRequest $request
     * @param ProviderExecutionReference $execution
     * @param string $status
     * @param array<int, GeneratedArtifactReference> $artifacts
     * @param string $errorCode
     * @param string $errorMessage
     * @param array<string, mixed> $overrides
     * @return GenerationResult
     *
     * @throws \InvalidArgumentException
     */
    private function build(
        GenerationRequest $request,
        ProviderExecutionReference $execution,
        $status,
        array $artifacts,
        $errorCode,
        $errorMessage,
        array $overrides
    ) {
        $this->assertRequestReady($request);
        $this->assertExecution($execution);

        $durationMs = isset($overrides['duration_ms'])
            ? (int) $overrides['duration_ms']
            : 0;
        if ($durationMs < 0) {
            throw new \InvalidArgumentException('duration_ms_invalid');
        }

        $bindingPayload = $this->bindingPayload(
            $request,
            $execution,
            $status,
            $artifacts,
            $errorCode,
            $errorMessage,
            $durationMs
        );
        $resultHash = $this->hashPayload($bindingPayload);
        $now = $this->utcNow();

        $data = array(
            'result_id' => isset($overrides['result_id'])
                ? (string) $overrides['result_id']
                : $this->newResultId($request->announcementId(), $resultHash),
            'request_id' => $request->requestId(),
            'request_hash' => $request->requestHash(),
            'announcement_id' => $request->announcementId(),
            'package_id' => $request->packageId(),
            'package_hash' => $request->packageHash(),
            'status' => $status,
            'provider_execution' => $execution,
            'artifacts' => $artifacts,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'duration_ms' => $durationMs,
            'result_hash' => $resultHash,
            'created_at_utc' => $now,
        );

        return new GenerationResult($data);
    }

    /**
     * @param GenerationRequest $request
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertRequestReady(GenerationRequest $request)
    {
        if ($request->requestId() === '') {
            throw new \InvalidArgumentException('request_id_required');
        }

        if ($request->requestHash() === '' || strlen($request->requestHash()) !== 64) {
            throw new \InvalidArgumentException('request_hash_invalid');
        }

        if ($request->announcementId() === '') {
            throw new \InvalidArgumentException('announcement_id_required');
        }

        if ($request->packageId() === '' || $request->packageHash() === '') {
            throw new \InvalidArgumentException('package_identity_required');
        }

        if ($request->status() !== GenerationRequestStatus::READY) {
            throw new \InvalidArgumentException('request_not_ready');
        }
    }

    /**
     * @param ProviderExecutionReference $execution
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function assertExecution(ProviderExecutionReference $execution)
    {
        if ($execution->executionId() === '') {
            throw new \InvalidArgumentException('execution_id_required');
        }

        if ($execution->providerCode() === '') {
            throw new \InvalidArgumentException('provider_code_required');
        }
    }

    /**
     * @param array<int, GeneratedArtifactReference|array<string, mixed>> $artifacts
     * @return array<int, GeneratedArtifactReference>
     *
     * @throws \InvalidArgumentException
     */
    private function normalizeArtifacts(array $artifacts)
    {
        $out = array();

        foreach ($artifacts as $artifact) {
            if ($artifact instanceof GeneratedArtifactReference) {
                $ref = $artifact;
            } elseif (is_array($artifact)) {
                $ref = new GeneratedArtifactReference($artifact);
            } else {
                throw new \InvalidArgumentException('artifact_invalid');
            }

            if ($ref->artifactId() === '') {
                throw new \InvalidArgumentException('artifact_id_required');
            }

            if (
                $ref->artifactKind() !== GeneratedArtifactReference::KIND_CONTENT_CANDIDATE
                && $ref->artifactKind() !== GeneratedArtifactReference::KIND_STRUCTURED_CANDIDATE
            ) {
                throw new \InvalidArgumentException('artifact_kind_invalid');
            }

            $out[] = $ref;
        }

        return $out;
    }

    /**
     * Canonical binding payload for deterministic result_hash.
     * Excludes result_id and created_at_utc.
     *
     * @param GenerationRequest $request
     * @param ProviderExecutionReference $execution
     * @param string $status
     * @param array<int, GeneratedArtifactReference> $artifacts
     * @param string $errorCode
     * @param string $errorMessage
     * @param int $durationMs
     * @return array<string, mixed>
     */
    private function bindingPayload(
        GenerationRequest $request,
        ProviderExecutionReference $execution,
        $status,
        array $artifacts,
        $errorCode,
        $errorMessage,
        $durationMs
    ) {
        $artifactPayload = array();
        foreach ($artifacts as $artifact) {
            $artifactPayload[] = $artifact->toArray();
        }

        return array(
            'request_id' => $request->requestId(),
            'request_hash' => $request->requestHash(),
            'announcement_id' => $request->announcementId(),
            'package_id' => $request->packageId(),
            'package_hash' => $request->packageHash(),
            'status' => $status,
            'provider_execution' => $execution->toArray(),
            'artifacts' => $artifactPayload,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'duration_ms' => (int) $durationMs,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    private function hashPayload(array $payload)
    {
        $canonical = $this->canonicalize($payload);
        $encoded = json_encode($canonical);

        if (!is_string($encoded) || $encoded === '') {
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value !== array() && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        $out = array();
        foreach ($value as $key => $item) {
            $out[$key] = $this->canonicalize($item);
        }

        return $out;
    }

    /**
     * @param string $announcementId
     * @param string $resultHash
     * @return string
     */
    private function newResultId($announcementId, $resultHash)
    {
        return 'gres_' . $announcementId . '_' . substr((string) $resultHash, 0, 12);
    }

    /**
     * @return string
     */
    private function utcNow()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
