<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Evidence\Evidence;

defined('ABSPATH') || exit;

final class AcquisitionResult
{
    private $success;
    /** @var array<int, string> */
    private $warnings;
    /** @var array<int, string> */
    private $errors;
    private $evidence;
    private $parserUsed;
    private $durationMs;
    private $metrics;
    private $parseResult;
    private $fetchResult;
    private $errorCode;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) && $data['success'] === true;
        $this->warnings = isset($data['warnings']) && is_array($data['warnings'])
            ? array_values($data['warnings'])
            : array();
        $this->errors = isset($data['errors']) && is_array($data['errors'])
            ? array_values($data['errors'])
            : array();
        $this->evidence = isset($data['evidence']) && $data['evidence'] instanceof Evidence
            ? $data['evidence']
            : null;
        $this->parserUsed = isset($data['parser_used']) ? (string) $data['parser_used'] : '';
        $this->durationMs = isset($data['duration']) ? (float) $data['duration'] : 0.0;
        $this->metrics = isset($data['metrics']) && $data['metrics'] instanceof CollectorMetrics
            ? $data['metrics']
            : new CollectorMetrics();
        $this->parseResult = isset($data['parse_result']) && is_array($data['parse_result'])
            ? $data['parse_result']
            : array();
        $this->fetchResult = isset($data['fetch_result']) && is_array($data['fetch_result'])
            ? $data['fetch_result']
            : array();
        $this->errorCode = isset($data['error_code']) ? (string) $data['error_code'] : '';
    }

    /** @return bool */
    public function success()
    {
        return $this->success;
    }

    /**
     * @return array<int, string>
     */
    public function warnings()
    {
        return $this->warnings;
    }

    /**
     * @return array<int, string>
     */
    public function errors()
    {
        return $this->errors;
    }

    /** @return Evidence|null */
    public function evidence()
    {
        return $this->evidence;
    }

    /** @return string */
    public function parserUsed()
    {
        return $this->parserUsed;
    }

    /** @return float */
    public function duration()
    {
        return $this->durationMs;
    }

    /** @return CollectorMetrics */
    public function metrics()
    {
        return $this->metrics;
    }

    /**
     * @return array<string, mixed>
     */
    public function parseResult()
    {
        return $this->parseResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchResult()
    {
        return $this->fetchResult;
    }

    /** @return string */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'success' => $this->success,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'evidence' => $this->evidence instanceof Evidence ? $this->evidence->toArray() : null,
            'parser_used' => $this->parserUsed,
            'duration' => $this->durationMs,
            'metrics' => $this->metrics->toArray(),
            'error_code' => $this->errorCode,
        );
    }
}
