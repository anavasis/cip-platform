<?php

namespace StudyMentor\ContentEngine\Evidence;

defined('ABSPATH') || exit;

/**
 * Immutable acquisition evidence package.
 */
final class Evidence
{
    private $source;
    private $url;
    private $fetchedAt;
    private $httpStatus;
    /** @var array<string, string> */
    private $headers;
    private $mimeType;
    private $body;
    private $contentHash;
    private $fetchDuration;
    private $collector;
    private $parserProfile;
    private $bodyHash;
    private $identityHash;
    private $finalUrl;
    private $responseBytes;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->source = isset($data['source']) ? (string) $data['source'] : '';
        $this->url = isset($data['url']) ? (string) $data['url'] : '';
        $this->fetchedAt = isset($data['fetched_at']) ? (string) $data['fetched_at'] : '';
        $this->httpStatus = isset($data['http_status']) ? (int) $data['http_status'] : 0;
        $this->headers = isset($data['headers']) && is_array($data['headers'])
            ? $data['headers']
            : array();
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
    }

    /** @return string */
    public function source()
    {
        return $this->source;
    }

    /** @return string */
    public function url()
    {
        return $this->url;
    }

    /** @return string */
    public function fetchedAt()
    {
        return $this->fetchedAt;
    }

    /** @return int */
    public function httpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, string>
     */
    public function headers()
    {
        return $this->headers;
    }

    /** @return string */
    public function mimeType()
    {
        return $this->mimeType;
    }

    /** @return string */
    public function body()
    {
        return $this->body;
    }

    /** @return string */
    public function contentHash()
    {
        return $this->contentHash;
    }

    /** @return float */
    public function fetchDuration()
    {
        return $this->fetchDuration;
    }

    /** @return string */
    public function collector()
    {
        return $this->collector;
    }

    /** @return string */
    public function parserProfile()
    {
        return $this->parserProfile;
    }

    /** @return string */
    public function bodyHash()
    {
        return $this->bodyHash;
    }

    /** @return string */
    public function identityHash()
    {
        return $this->identityHash;
    }

    /** @return string */
    public function finalUrl()
    {
        return $this->finalUrl;
    }

    /** @return int */
    public function responseBytes()
    {
        return $this->responseBytes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'source' => $this->source,
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
        );
    }
}
