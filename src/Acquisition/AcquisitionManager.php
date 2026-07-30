<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Evidence\Evidence;
use StudyMentor\ContentEngine\Evidence\EvidenceRepositoryInterface;
use StudyMentor\ContentEngine\Fingerprint\FingerprintService;
use StudyMentor\ContentEngine\Registry\ParserRegistry;

defined('ABSPATH') || exit;

/**
 * Orchestrates: Collector → Download → Evidence → Fingerprint → Parser.
 */
final class AcquisitionManager
{
    private $collectorRegistry;
    private $downloadManager;
    private $fingerprintService;
    private $evidenceRepository;
    private $parserRegistry;

    public function __construct(
        CollectorRegistry $collectorRegistry,
        DownloadManager $downloadManager,
        FingerprintService $fingerprintService,
        EvidenceRepositoryInterface $evidenceRepository,
        ParserRegistry $parserRegistry
    ) {
        $this->collectorRegistry = $collectorRegistry;
        $this->downloadManager = $downloadManager;
        $this->fingerprintService = $fingerprintService;
        $this->evidenceRepository = $evidenceRepository;
        $this->parserRegistry = $parserRegistry;
    }

    /**
     * @param array<string, mixed> $request
     * @return AcquisitionResult
     */
    public function acquire(array $request)
    {
        $startedAt = microtime(true);
        $warnings = array();
        $errors = array();

        $url = isset($request['url']) ? trim((string) $request['url']) : '';
        $allowedDomains = isset($request['allowed_domains']) && is_array($request['allowed_domains'])
            ? $request['allowed_domains']
            : array();
        $sourceType = isset($request['source_type'])
            ? strtolower(trim((string) $request['source_type']))
            : '';
        $parserProfile = isset($request['parser_profile'])
            ? trim((string) $request['parser_profile'])
            : '';
        $sourceKey = isset($request['source_key'])
            ? (string) $request['source_key']
            : (isset($request['source_id']) ? (string) $request['source_id'] : '');
        $collectorId = isset($request['collector_id'])
            ? trim((string) $request['collector_id'])
            : '';

        $parser = $this->parserRegistry->resolve($sourceType, $parserProfile);

        if ($parser === null) {
            $errorCode = $sourceType === 'html'
                ? 'unsupported_parser_profile'
                : 'unsupported_source_type';

            return $this->failureResult(
                $errorCode,
                array($errorCode),
                $warnings,
                $startedAt,
                new CollectorMetrics(),
                array(),
                array()
            );
        }

        $collector = $collectorId !== ''
            ? $this->collectorRegistry->get($collectorId)
            : $this->collectorRegistry->resolveForSourceType($sourceType);

        if ($collector === null) {
            $collector = $this->collectorRegistry->defaultCollector();
        }

        if ($collector === null) {
            return $this->failureResult(
                'transport_error',
                array('collector_unavailable'),
                $warnings,
                $startedAt,
                new CollectorMetrics(),
                array(),
                array()
            );
        }

        if ($url === '') {
            return $this->failureResult(
                'missing_feed_url',
                array('missing_feed_url'),
                $warnings,
                $startedAt,
                new CollectorMetrics(array('collector' => $collector->id())),
                array(),
                array()
            );
        }

        if ($allowedDomains === array()) {
            return $this->failureResult(
                'allowed_domains_invalid',
                array('allowed_domains_invalid'),
                $warnings,
                $startedAt,
                new CollectorMetrics(array('collector' => $collector->id())),
                array(),
                array()
            );
        }

        $family = $this->downloadManager->familyForSourceType($sourceType);
        $download = $this->downloadManager->download(
            $collector,
            $url,
            $allowedDomains,
            $family
        );

        $fetchResult = $download['fetch_result'];
        $metrics = $download['metrics'];
        $durationSeconds = (float) $download['duration_seconds'];

        if (!isset($fetchResult['success']) || $fetchResult['success'] !== true) {
            $errorCode = isset($fetchResult['error_code']) && $fetchResult['error_code'] !== ''
                ? (string) $fetchResult['error_code']
                : 'transport_error';

            return $this->failureResult(
                $errorCode,
                array($errorCode),
                $warnings,
                $startedAt,
                $metrics,
                $fetchResult,
                array()
            );
        }

        $body = isset($fetchResult['body']) ? (string) $fetchResult['body'] : '';
        $finalUrl = isset($fetchResult['final_url'])
            ? (string) $fetchResult['final_url']
            : $url;
        $fingerprints = $this->fingerprintService->fingerprint($body, $finalUrl, $sourceKey);

        $fetchedAt = function_exists('current_time')
            ? (string) current_time('mysql', true)
            : gmdate('Y-m-d H:i:s');

        $headers = array();

        if (isset($fetchResult['content_type']) && is_string($fetchResult['content_type'])
            && $fetchResult['content_type'] !== ''
        ) {
            $headers['content-type'] = (string) $fetchResult['content_type'];
        }

        $evidence = new Evidence(array(
            'source' => $sourceKey,
            'source_type' => $sourceType,
            'url' => isset($fetchResult['requested_url'])
                ? (string) $fetchResult['requested_url']
                : $url,
            'fetched_at' => $fetchedAt,
            'http_status' => isset($fetchResult['http_status'])
                ? (int) $fetchResult['http_status']
                : 0,
            'headers' => $headers,
            'mime_type' => isset($fetchResult['content_type'])
                ? (string) $fetchResult['content_type']
                : '',
            'body' => $body,
            'content_hash' => $fingerprints['content_hash'],
            'fetch_duration' => $durationSeconds,
            'collector' => $collector->id(),
            'parser_profile' => $parserProfile,
            'body_hash' => $fingerprints['body_hash'],
            'identity_hash' => $fingerprints['identity_hash'],
            'final_url' => $finalUrl,
            'response_bytes' => isset($fetchResult['response_size'])
                ? (int) $fetchResult['response_size']
                : strlen($body),
        ));

        $this->evidenceRepository->store($evidence);

        $parseResult = $parser->parse(
            $evidence->body(),
            $evidence->mimeType(),
            $parserProfile,
            $evidence->finalUrl(),
            $allowedDomains
        );

        $parserUsed = get_class($parser);
        $totalDurationMs = round((microtime(true) - $startedAt) * 1000, 3);

        if (!isset($parseResult['success']) || $parseResult['success'] !== true) {
            $parserError = isset($parseResult['error_code'])
                ? (string) $parseResult['error_code']
                : 'invalid_feed_content';
            $errors[] = $parserError;

            return new AcquisitionResult(array(
                'success' => false,
                'warnings' => $warnings,
                'errors' => $errors,
                'evidence' => $evidence,
                'parser_used' => $parserUsed,
                'duration' => $totalDurationMs,
                'metrics' => $metrics,
                'parse_result' => is_array($parseResult) ? $parseResult : array(),
                'fetch_result' => $fetchResult,
                'error_code' => $parserError,
            ));
        }

        return new AcquisitionResult(array(
            'success' => true,
            'warnings' => $warnings,
            'errors' => array(),
            'evidence' => $evidence,
            'parser_used' => $parserUsed,
            'duration' => $totalDurationMs,
            'metrics' => $metrics,
            'parse_result' => $parseResult,
            'fetch_result' => $fetchResult,
            'error_code' => '',
        ));
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     * @param array<string, mixed> $fetchResult
     * @param array<string, mixed> $parseResult
     * @return AcquisitionResult
     */
    private function failureResult(
        $errorCode,
        array $errors,
        array $warnings,
        $startedAt,
        CollectorMetrics $metrics,
        array $fetchResult,
        array $parseResult
    ) {
        return new AcquisitionResult(array(
            'success' => false,
            'warnings' => $warnings,
            'errors' => $errors,
            'evidence' => null,
            'parser_used' => '',
            'duration' => round((microtime(true) - (float) $startedAt) * 1000, 3),
            'metrics' => $metrics,
            'parse_result' => $parseResult,
            'fetch_result' => $fetchResult,
            'error_code' => (string) $errorCode,
        ));
    }
}
