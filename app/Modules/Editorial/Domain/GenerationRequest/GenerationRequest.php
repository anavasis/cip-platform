<?php

namespace App\Modules\Editorial\Domain\GenerationRequest;


/**
 * Canonical Generation Request aggregate (ADR-001).
 * Provider-independent intent to generate from a Prompt Package.
 * Not an execution record, result, queue job, or vendor call.
 */
final class GenerationRequest
{
    private $requestId;
    private $announcementId;
    private $lineageId;
    private $packageId;
    private $packageHash;
    private $modelReference;
    private $parameters;
    private $status;
    private $requestHash;
    private $createdAtUtc;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->requestId = isset($data['request_id']) ? (string) $data['request_id'] : '';
        $this->announcementId = isset($data['announcement_id']) ? trim((string) $data['announcement_id']) : '';
        $this->lineageId = isset($data['lineage_id']) ? (string) $data['lineage_id'] : '';
        $this->packageId = isset($data['package_id']) ? (string) $data['package_id'] : '';
        $this->packageHash = isset($data['package_hash']) ? (string) $data['package_hash'] : '';
        $this->modelReference = isset($data['model_reference'])
            && $data['model_reference'] instanceof GenerationModelReference
            ? $data['model_reference']
            : new GenerationModelReference(
                isset($data['model_reference']) && is_array($data['model_reference'])
                    ? $data['model_reference']
                    : array()
            );
        $this->parameters = isset($data['parameters']) && $data['parameters'] instanceof GenerationParameters
            ? $data['parameters']
            : new GenerationParameters(
                isset($data['parameters']) && is_array($data['parameters'])
                    ? $data['parameters']
                    : array()
            );
        $this->status = isset($data['status'])
            ? (string) $data['status']
            : GenerationRequestStatus::READY;
        $this->requestHash = isset($data['request_hash']) ? (string) $data['request_hash'] : '';
        $this->createdAtUtc = isset($data['created_at_utc']) ? (string) $data['created_at_utc'] : '';
    }

    /** @return string */
    public function requestId()
    {
        return $this->requestId;
    }

    /** @return string */
    public function announcementId()
    {
        return $this->announcementId;
    }

    /** @return string */
    public function lineageId()
    {
        return $this->lineageId;
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

    /** @return GenerationModelReference */
    public function modelReference()
    {
        return $this->modelReference;
    }

    /** @return GenerationParameters */
    public function parameters()
    {
        return $this->parameters;
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /** @return string */
    public function requestHash()
    {
        return $this->requestHash;
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
        return array(
            'request_id' => $this->requestId,
            'announcement_id' => $this->announcementId,
            'lineage_id' => $this->lineageId,
            'package_id' => $this->packageId,
            'package_hash' => $this->packageHash,
            'model_reference' => $this->modelReference->toArray(),
            'parameters' => $this->parameters->toArray(),
            'status' => $this->status,
            'request_hash' => $this->requestHash,
            'created_at_utc' => $this->createdAtUtc,
        );
    }
}
