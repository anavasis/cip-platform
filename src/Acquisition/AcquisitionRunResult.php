<?php

namespace StudyMentor\ContentEngine\Acquisition;

defined('ABSPATH') || exit;

/**
 * Immutable production acquisition run result.
 * Per-source entries are metadata-only (no evidence body).
 */
final class AcquisitionRunResult
{
    private $success;
    private $runId;
    private $errorCode;
    private $sourcesRequested;
    private $sourcesSucceeded;
    private $sourcesFailed;
    /** @var array<int, array<string, mixed>> */
    private $results;
    private $durationMs;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->runId = isset($data['run_id']) ? (string) $data['run_id'] : '';
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
        $this->sourcesRequested = isset($data['sources_requested'])
            ? (int) $data['sources_requested']
            : 0;
        $this->sourcesSucceeded = isset($data['sources_succeeded'])
            ? (int) $data['sources_succeeded']
            : 0;
        $this->sourcesFailed = isset($data['sources_failed'])
            ? (int) $data['sources_failed']
            : 0;
        $this->results = isset($data['results']) && is_array($data['results'])
            ? array_values($data['results'])
            : array();
        $this->durationMs = isset($data['duration_ms']) ? (float) $data['duration_ms'] : 0.0;
    }

    /** @return bool */
    public function success()
    {
        return $this->success;
    }

    /** @return string */
    public function runId()
    {
        return $this->runId;
    }

    /** @return string */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /** @return int */
    public function sourcesRequested()
    {
        return $this->sourcesRequested;
    }

    /** @return int */
    public function sourcesSucceeded()
    {
        return $this->sourcesSucceeded;
    }

    /** @return int */
    public function sourcesFailed()
    {
        return $this->sourcesFailed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function results()
    {
        return $this->results;
    }

    /** @return float */
    public function durationMs()
    {
        return $this->durationMs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'success' => $this->success,
            'run_id' => $this->runId,
            'error_code' => $this->errorCode,
            'sources_requested' => $this->sourcesRequested,
            'sources_succeeded' => $this->sourcesSucceeded,
            'sources_failed' => $this->sourcesFailed,
            'results' => $this->results,
            'duration_ms' => $this->durationMs,
        );
    }
}
