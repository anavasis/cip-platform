<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Collectors\CollectorInterface;

defined('ABSPATH') || exit;

/**
 * Unified download flow. Currently HTTP-feed oriented; future-ready for
 * RSS, HTML, JSON, XML, and PDF collectors sharing the same contract.
 */
final class DownloadManager
{
    public const FAMILY_RSS = 'rss';
    public const FAMILY_HTML = 'html';
    public const FAMILY_JSON = 'json';
    public const FAMILY_XML = 'xml';
    public const FAMILY_PDF = 'pdf';

    /**
     * @param CollectorInterface $collector
     * @param string $url
     * @param array<int, string> $allowedDomains
     * @param string $contentFamily Reserved for future collector routing.
     * @return array{
     *   fetch_result: array<string, mixed>,
     *   metrics: CollectorMetrics,
     *   duration_seconds: float
     * }
     */
    public function download(
        CollectorInterface $collector,
        $url,
        array $allowedDomains,
        $contentFamily = self::FAMILY_RSS
    ) {
        unset($contentFamily);

        $startedAt = microtime(true);
        $fetchResult = $collector->collect((string) $url, $allowedDomains);
        $durationSeconds = microtime(true) - $startedAt;

        if (!is_array($fetchResult)) {
            $fetchResult = array(
                'success' => false,
                'error_code' => 'transport_error',
                'requested_url' => (string) $url,
                'final_url' => '',
                'http_status' => 0,
                'content_type' => '',
                'response_size' => 0,
                'body' => '',
            );
        }

        $success = isset($fetchResult['success']) && $fetchResult['success'] === true;
        $bytes = isset($fetchResult['response_size'])
            ? (int) $fetchResult['response_size']
            : strlen(isset($fetchResult['body']) ? (string) $fetchResult['body'] : '');
        $httpStatus = isset($fetchResult['http_status']) ? (int) $fetchResult['http_status'] : 0;
        $redirects = 0;

        if (
            isset($fetchResult['requested_url'], $fetchResult['final_url'])
            && is_string($fetchResult['requested_url'])
            && is_string($fetchResult['final_url'])
            && $fetchResult['final_url'] !== ''
            && $fetchResult['requested_url'] !== $fetchResult['final_url']
        ) {
            $redirects = 1;
        }

        $metrics = new CollectorMetrics(array(
            'execution_time_ms' => round($durationSeconds * 1000, 3),
            'bytes' => max(0, $bytes),
            'redirects' => $redirects,
            'failures' => $success ? 0 : 1,
            'http_status' => $httpStatus,
            'collector' => $collector->id(),
        ));

        return array(
            'fetch_result' => $fetchResult,
            'metrics' => $metrics,
            'duration_seconds' => $durationSeconds,
        );
    }

    /**
     * @param string $sourceType
     * @return string
     */
    public function familyForSourceType($sourceType)
    {
        $type = strtolower(trim((string) $sourceType));

        if ($type === 'html') {
            return self::FAMILY_HTML;
        }

        if ($type === 'atom' || $type === 'rss') {
            return self::FAMILY_RSS;
        }

        if ($type === 'json') {
            return self::FAMILY_JSON;
        }

        if ($type === 'xml') {
            return self::FAMILY_XML;
        }

        if ($type === 'pdf') {
            return self::FAMILY_PDF;
        }

        return self::FAMILY_RSS;
    }
}
