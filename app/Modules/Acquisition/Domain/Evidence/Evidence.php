<?php

namespace App\Modules\Acquisition\Domain\Evidence;

/**
 * Immutable acquisition evidence package.
 */
final readonly class Evidence
{
    /** @var array<string, string> */
    private array $headers;

    private string $source;

    private string $sourceType;

    private string $url;

    private string $fetchedAt;

    private int $httpStatus;

    private string $mimeType;

    private string $body;

    private string $contentHash;

    private float $fetchDuration;

    private string $collector;

    private string $parserProfile;

    private string $bodyHash;

    private string $identityHash;

    private string $finalUrl;

    private int $responseBytes;

    private string $organizationId;

    private string $projectId;

    private string $correlationId;

    private string $runId;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->source = isset($data['source']) ? (string) $data['source'] : '';
        $this->sourceType = isset($data['source_type']) ? (string) $data['source_type'] : '';
        $this->url = isset($data['url']) ? (string) $data['url'] : '';
        $this->fetchedAt = isset($data['fetched_at']) ? (string) $data['fetched_at'] : '';
        $this->httpStatus = isset($data['http_status']) ? (int) $data['http_status'] : 0;
        $this->headers = isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [];
        $this->mimeType = isset($data['mime_type']) ? (string) $data['mime_type'] : '';
        $this->body = isset($data['body']) ? (string) $data['body'] : '';
        $this->contentHash = isset($data['content_hash']) ? (string) $data['content_hash'] : '';
        $this->fetchDuration = isset($data['fetch_duration']) ? (float) $data['fetch_duration'] : 0.0;
        $this->collector = isset($data['collector']) ? (string) $data['collector'] : '';
        $this->parserProfile = isset($data['parser_profile']) ? (string) $data['parser_profile'] : '';
        $this->bodyHash = isset($data['body_hash']) ? (string) $data['body_hash'] : '';
        $this->identityHash = isset($data['identity_hash']) ? (string) $data['identity_hash'] : '';
        $this->finalUrl = isset($data['final_url']) ? (string) $data['final_url'] : $this->url;
        $this->responseBytes = isset($data['response_bytes'])
            ? (int) $data['response_bytes']
            : strlen($this->body);
        $this->organizationId = isset($data['organization_id']) ? (string) $data['organization_id'] : '';
        $this->projectId = isset($data['project_id']) ? (string) $data['project_id'] : '';
        $this->correlationId = isset($data['correlation_id']) ? (string) $data['correlation_id'] : '';
        $this->runId = isset($data['run_id']) ? (string) $data['run_id'] : '';
    }

    public function source(): string
    {
        return $this->source;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function fetchedAt(): string
    {
        return $this->fetchedAt;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function fetchDuration(): float
    {
        return $this->fetchDuration;
    }

    public function collector(): string
    {
        return $this->collector;
    }

    public function parserProfile(): string
    {
        return $this->parserProfile;
    }

    public function bodyHash(): string
    {
        return $this->bodyHash;
    }

    public function identityHash(): string
    {
        return $this->identityHash;
    }

    public function finalUrl(): string
    {
        return $this->finalUrl;
    }

    public function responseBytes(): int
    {
        return $this->responseBytes;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function runId(): string
    {
        return $this->runId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'source_type' => $this->sourceType,
            'url' => $this->url,
            'fetched_at' => $this->fetchedAt,
            'http_status' => $this->httpStatus,
            'headers' => $this->headers,
            'mime_type' => $this->mimeType,
            'body' => $this->body,
            'content_hash' => $this->contentHash,
            'fetch_duration' => $this->fetchDuration,
            'collector' => $this->collector,
            'parser_profile' => $this->parserProfile,
            'body_hash' => $this->bodyHash,
            'identity_hash' => $this->identityHash,
            'final_url' => $this->finalUrl,
            'response_bytes' => $this->responseBytes,
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'correlation_id' => $this->correlationId,
            'run_id' => $this->runId,
        ];
    }

    /** @return array<string, mixed> */
    public function toMetadataArray(): array
    {
        return [
            'source' => $this->source,
            'source_type' => $this->sourceType,
            'url' => $this->url,
            'fetched_at' => $this->fetchedAt,
            'http_status' => $this->httpStatus,
            'headers' => $this->headers,
            'mime_type' => $this->mimeType,
            'body_omitted' => true,
            'content_hash' => $this->contentHash,
            'fetch_duration' => $this->fetchDuration,
            'collector' => $this->collector,
            'parser_profile' => $this->parserProfile,
            'body_hash' => $this->bodyHash,
            'identity_hash' => $this->identityHash,
            'final_url' => $this->finalUrl,
            'response_bytes' => $this->responseBytes,
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'correlation_id' => $this->correlationId,
            'run_id' => $this->runId,
        ];
    }
}
