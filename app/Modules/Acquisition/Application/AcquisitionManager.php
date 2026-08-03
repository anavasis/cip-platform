<?php

namespace App\Modules\Acquisition\Application;

use App\Modules\Acquisition\Domain\AcquisitionResult;
use App\Modules\Acquisition\Domain\CollectorMetrics;
use App\Modules\Acquisition\Domain\Collectors\CollectorRegistry;
use App\Modules\Acquisition\Domain\Evidence\Evidence;
use App\Modules\Acquisition\Domain\Evidence\EvidenceRepositoryInterface;
use App\Modules\Acquisition\Domain\Fingerprint\FingerprintService;
use App\Modules\Acquisition\Domain\Registry\ParserRegistry;

/**
 * Orchestrates Collector → Download → Evidence → Fingerprint → Parser.
 */
final readonly class AcquisitionManager
{
    public function __construct(
        private CollectorRegistry $collectorRegistry,
        private DownloadManager $downloadManager,
        private FingerprintService $fingerprintService,
        private EvidenceRepositoryInterface $evidenceRepository,
        private ParserRegistry $parserRegistry,
    ) {}

    /** @param array<string, mixed> $request */
    public function acquire(array $request): AcquisitionResult
    {
        $startedAt = microtime(true);
        $warnings = [];
        $url = isset($request['url']) ? trim((string) $request['url']) : '';
        $allowedDomains = isset($request['allowed_domains']) && is_array($request['allowed_domains'])
            ? $request['allowed_domains']
            : [];
        $sourceType = isset($request['source_type'])
            ? strtolower(trim((string) $request['source_type']))
            : '';
        $parserProfile = isset($request['parser_profile']) ? trim((string) $request['parser_profile']) : '';
        $sourceKey = isset($request['source_key'])
            ? (string) $request['source_key']
            : (isset($request['source_id']) ? (string) $request['source_id'] : '');
        $collectorId = isset($request['collector_id']) ? trim((string) $request['collector_id']) : '';
        $parser = $this->parserRegistry->resolve($sourceType, $parserProfile);

        if ($parser === null) {
            $errorCode = $sourceType === 'html'
                ? 'unsupported_parser_profile'
                : 'unsupported_source_type';

            return $this->failureResult(
                $errorCode,
                [$errorCode],
                $warnings,
                $startedAt,
                new CollectorMetrics,
            );
        }

        $collector = $collectorId !== ''
            ? $this->collectorRegistry->get($collectorId)
            : $this->collectorRegistry->resolveForSourceType($sourceType);
        $collector ??= $this->collectorRegistry->defaultCollector();

        if ($collector === null) {
            return $this->failureResult(
                'transport_error',
                ['collector_unavailable'],
                $warnings,
                $startedAt,
                new CollectorMetrics,
            );
        }

        if ($url === '') {
            return $this->failureResult(
                'missing_feed_url',
                ['missing_feed_url'],
                $warnings,
                $startedAt,
                new CollectorMetrics(['collector' => $collector->id()]),
            );
        }

        if ($allowedDomains === []) {
            return $this->failureResult(
                'allowed_domains_invalid',
                ['allowed_domains_invalid'],
                $warnings,
                $startedAt,
                new CollectorMetrics(['collector' => $collector->id()]),
            );
        }

        $download = $this->downloadManager->download(
            $collector,
            $url,
            $allowedDomains,
            $this->downloadManager->familyForSourceType($sourceType),
        );
        $fetchResult = $download['fetch_result'];
        $metrics = $download['metrics'];
        $durationSeconds = $download['duration_seconds'];

        if (($fetchResult['success'] ?? false) !== true) {
            $errorCode = isset($fetchResult['error_code']) && $fetchResult['error_code'] !== ''
                ? (string) $fetchResult['error_code']
                : 'transport_error';

            return $this->failureResult(
                $errorCode,
                [$errorCode],
                $warnings,
                $startedAt,
                $metrics,
                $fetchResult,
            );
        }

        $body = isset($fetchResult['body']) ? (string) $fetchResult['body'] : '';
        $finalUrl = isset($fetchResult['final_url']) ? (string) $fetchResult['final_url'] : $url;
        $fingerprints = $this->fingerprintService->fingerprint($body, $finalUrl, $sourceKey);
        $headers = [];

        if (isset($fetchResult['content_type']) && is_string($fetchResult['content_type'])
            && $fetchResult['content_type'] !== '') {
            $headers['content-type'] = $fetchResult['content_type'];
        }

        $evidence = new Evidence([
            'source' => $sourceKey,
            'source_type' => $sourceType,
            'url' => isset($fetchResult['requested_url']) ? (string) $fetchResult['requested_url'] : $url,
            'fetched_at' => gmdate('Y-m-d H:i:s'),
            'http_status' => isset($fetchResult['http_status']) ? (int) $fetchResult['http_status'] : 0,
            'headers' => $headers,
            'mime_type' => isset($fetchResult['content_type']) ? (string) $fetchResult['content_type'] : '',
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
        ]);

        $this->evidenceRepository->store($evidence);
        $parseResult = $parser->parse(
            $evidence->body(),
            $evidence->mimeType(),
            $parserProfile,
            $evidence->finalUrl(),
            $allowedDomains,
        );
        $parserUsed = $parser::class;
        $totalDurationMs = round((microtime(true) - $startedAt) * 1000, 3);

        if (($parseResult['success'] ?? false) !== true) {
            $parserError = isset($parseResult['error_code'])
                ? (string) $parseResult['error_code']
                : 'invalid_feed_content';

            return new AcquisitionResult([
                'success' => false,
                'warnings' => $warnings,
                'errors' => [$parserError],
                'evidence' => $evidence,
                'parser_used' => $parserUsed,
                'duration' => $totalDurationMs,
                'metrics' => $metrics,
                'parse_result' => $parseResult,
                'fetch_result' => $fetchResult,
                'error_code' => $parserError,
            ]);
        }

        return new AcquisitionResult([
            'success' => true,
            'warnings' => $warnings,
            'errors' => [],
            'evidence' => $evidence,
            'parser_used' => $parserUsed,
            'duration' => $totalDurationMs,
            'metrics' => $metrics,
            'parse_result' => $parseResult,
            'fetch_result' => $fetchResult,
            'error_code' => '',
        ]);
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $fetchResult
     * @param  array<string, mixed>  $parseResult
     */
    private function failureResult(
        string $errorCode,
        array $errors,
        array $warnings,
        float $startedAt,
        CollectorMetrics $metrics,
        array $fetchResult = [],
        array $parseResult = [],
    ): AcquisitionResult {
        return new AcquisitionResult([
            'success' => false,
            'warnings' => $warnings,
            'errors' => $errors,
            'evidence' => null,
            'parser_used' => '',
            'duration' => round((microtime(true) - $startedAt) * 1000, 3),
            'metrics' => $metrics,
            'parse_result' => $parseResult,
            'fetch_result' => $fetchResult,
            'error_code' => $errorCode,
        ]);
    }
}
