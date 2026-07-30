<?php

namespace StudyMentor\ContentEngine\Acquisition;

defined('ABSPATH') || exit;

final class CollectorMetrics
{
    private $executionTimeMs;
    private $bytes;
    private $redirects;
    private $failures;
    private $httpStatus;
    private $collectorId;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = array())
    {
        $this->executionTimeMs = isset($data['execution_time_ms'])
            ? (float) $data['execution_time_ms']
            : 0.0;
        $this->bytes = isset($data['bytes']) ? (int) $data['bytes'] : 0;
        $this->redirects = isset($data['redirects']) ? (int) $data['redirects'] : 0;
        $this->failures = isset($data['failures']) ? (int) $data['failures'] : 0;
        $this->httpStatus = isset($data['http_status']) ? (int) $data['http_status'] : 0;
        $this->collectorId = isset($data['collector']) ? (string) $data['collector'] : '';
    }

    /** @return float */
    public function executionTimeMs()
    {
        return $this->executionTimeMs;
    }

    /** @return int */
    public function bytes()
    {
        return $this->bytes;
    }

    /** @return int */
    public function redirects()
    {
        return $this->redirects;
    }

    /** @return int */
    public function failures()
    {
        return $this->failures;
    }

    /** @return int */
    public function httpStatus()
    {
        return $this->httpStatus;
    }

    /** @return string */
    public function collectorId()
    {
        return $this->collectorId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'execution_time_ms' => $this->executionTimeMs,
            'bytes' => $this->bytes,
            'redirects' => $this->redirects,
            'failures' => $this->failures,
            'http_status' => $this->httpStatus,
            'collector' => $this->collectorId,
        );
    }
}
