<?php

namespace StudyMentor\ContentEngine\GenerationResult;

defined('ABSPATH') || exit;

/**
 * Canonical Generation Result aggregate (ADR-001).
 * Immutable provider-agnostic outcome envelope for a Generation Request.
 * Not a provider adapter, queue job, or article body store.
 */
final class GenerationResult
{
    private $resultId;
    private $requestId;
    private $requestHash;
    private $announcementId;
    private $packageId;
    private $packageHash;
    private $status;
    private $providerExecution;
    /** @var array<int, GeneratedArtifactReference> */
    private $artifacts;
    private $errorCode;
    private $errorMessage;
    private $durationMs;
    private $resultHash;
    private $createdAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->resultId = isset($data['result_id']) ? (string) $data['result_id'] : '';
        $this->requestId = isset($data['request_id']) ? (string) $data['request_id'] : '';
        $this->requestHash = isset($data['request_hash']) ? (string) $data['request_hash'] : '';
        $this->announcementId = isset($data['announcement_id']) ? (int) $data['announcement_id'] : 0;
        $this->packageId = isset($data['package_id']) ? (string) $data['package_id'] : '';
        $this->packageHash = isset($data['package_hash']) ? (string) $data['package_hash'] : '';
        $this->status = isset($data['status'])
            ? (string) $data['status']
            : GenerationResultStatus::SUCCESS;
        $this->providerExecution = isset($data['provider_execution'])
            && $data['provider_execution'] instanceof ProviderExecutionReference
            ? $data['provider_execution']
            : new ProviderExecutionReference(
                isset($data['provider_execution']) && is_array($data['provider_execution'])
                    ? $data['provider_execution']
                    : array()
            );
        $this->artifacts = $this->mapArtifacts(
            isset($data['artifacts']) ? $data['artifacts'] : array()
        );
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
        $this->errorMessage = isset($data['error_message']) ? (string) $data['error_message'] : '';
        $this->durationMs = isset($data['duration_ms']) ? (int) $data['duration_ms'] : 0;
        $this->resultHash = isset($data['result_hash']) ? (string) $data['result_hash'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
    }

    /** @return string */
    public function resultId()
    {
        return $this->resultId;
    }

    /** @return string */
    public function requestId()
    {
        return $this->requestId;
    }

    /** @return string */
    public function requestHash()
    {
        return $this->requestHash;
    }

    /** @return int */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function packageId()
    {
        return $this->packageId;
    }

    /** @return string */
    public function packageHash()
    {
        return $this->packageHash;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return ProviderExecutionReference */
    public function providerExecution()
    {
        return $this->providerExecution;
    }

    /**
     * @return array<int, GeneratedArtifactReference>
     */
    public function artifacts()
    {
        return $this->artifacts;
    }

    /** @return string */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /** @return string */
    public function errorMessage()
    {
        return $this->errorMessage;
    }

    /** @return int */
    public function durationMs()
    {
        return $this->durationMs;
    }

    /** @return string */
    public function resultHash()
    {
        return $this->resultHash;
    }

    /** @return string */
    public function createdAtUtc()
    {
        return $this->createdAtUtc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $artifacts = array();
        foreach ($this->artifacts as $artifact) {
            $artifacts[] = $artifact->toArray();
        }

        return array(
            'result_id' => $this->resultId,
            'request_id' => $this->requestId,
            'request_hash' => $this->requestHash,
            'announcement_id' => $this->announcementId,
            'package_id' => $this->packageId,
            'package_hash' => $this->packageHash,
            'status' => $this->status,
            'provider_execution' => $this->providerExecution->toArray(),
            'artifacts' => $artifacts,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'duration_ms' => $this->durationMs,
            'result_hash' => $this->resultHash,
            'created_at_utc' => $this->createdAtUtc,
        );
    }

    /**
     * @param mixed $items
     * @return array<int, GeneratedArtifactReference>
     */
    private function mapArtifacts($items)
    {
        $out = array();

        if (!is_array($items)) {
            return $out;
        }

        foreach ($items as $item) {
            if ($item instanceof GeneratedArtifactReference) {
                $out[] = $item;
                continue;
            }

            if (is_array($item)) {
                $out[] = new GeneratedArtifactReference($item);
            }
        }

        return $out;
    }
}
