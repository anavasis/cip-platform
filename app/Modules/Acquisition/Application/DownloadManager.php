<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\CollectorMetrics;
use App\Modules\Acquisition\Domain\Collectors\CollectorInterface;

final class DownloadManager
{
    public const FAMILY_RSS = 'rss';

    public const FAMILY_HTML = 'html';

    public const FAMILY_JSON = 'json';

    public const FAMILY_XML = 'xml';

    public const FAMILY_PDF = 'pdf';

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array{
     *   fetch_result: array<string, mixed>,
     *   metrics: CollectorMetrics,
     *   duration_seconds: float
     * }
     */
    public function download(
        CollectorInterface $collector,
        string $url,
        array $allowedDomains,
        string $contentFamily = self::FAMILY_RSS,
    ): array {
        unset($contentFamily);

        $startedAt = microtime(true);
        $fetchResult = $collector->collect($url, $allowedDomains);
        $durationSeconds = microtime(true) - $startedAt;
        $success = ($fetchResult['success'] ?? false) === true;
        $bytes = isset($fetchResult['response_size'])
            ? (int) $fetchResult['response_size']
            : strlen(isset($fetchResult['body']) ? (string) $fetchResult['body'] : '');
        $httpStatus = isset($fetchResult['http_status']) ? (int) $fetchResult['http_status'] : 0;
        $redirects = isset($fetchResult['requested_url'], $fetchResult['final_url'])
            && is_string($fetchResult['requested_url'])
            && is_string($fetchResult['final_url'])
            && $fetchResult['final_url'] !== ''
            && $fetchResult['requested_url'] !== $fetchResult['final_url']
            ? 1
            : 0;

        return [
            'fetch_result' => $fetchResult,
            'metrics' => new CollectorMetrics([
                'execution_time_ms' => round($durationSeconds * 1000, 3),
                'bytes' => max(0, $bytes),
                'redirects' => $redirects,
                'failures' => $success ? 0 : 1,
                'http_status' => $httpStatus,
                'collector' => $collector->id(),
            ]),
            'duration_seconds' => $durationSeconds,
        ];
    }

    public function familyForSourceType(string $sourceType): string
    {
        return match (strtolower(trim($sourceType))) {
            'html' => self::FAMILY_HTML,
            'atom', 'rss' => self::FAMILY_RSS,
            'json' => self::FAMILY_JSON,
            'xml' => self::FAMILY_XML,
            'pdf' => self::FAMILY_PDF,
            default => self::FAMILY_RSS,
        };
    }
}
