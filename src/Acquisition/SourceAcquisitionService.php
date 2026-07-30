<?php

namespace StudyMentor\ContentEngine\Acquisition;

use StudyMentor\ContentEngine\Data\SourceRepository;

defined('ABSPATH') || exit;

/**
 * Canonical source-to-acquisition entry point for collector execution.
 */
final class SourceAcquisitionService
{
    private $repository;
    private $acquisitionEngine;

    public function __construct(
        SourceRepository $repository,
        AcquisitionEngine $acquisitionEngine
    ) {
        $this->repository = $repository;
        $this->acquisitionEngine = $acquisitionEngine;
    }

    /**
     * @param int|string $sourceId
     * @return AcquisitionResult
     */
    public function acquireFromSource($sourceId)
    {
        $id = (int) $sourceId;

        if ($id <= 0) {
            return $this->preAcquireError('invalid_id');
        }

        $source = $this->repository->findById($id);

        if ($source === null) {
            return $this->preAcquireError('not_found');
        }

        return $this->acquire($this->buildRequestFromSource($source, $id));
    }

    /**
     * @param array<string, mixed> $request
     * @return AcquisitionResult
     */
    public function acquire(array $request)
    {
        return $this->acquisitionEngine->acquire($request);
    }

    /**
     * @param array<string, mixed> $source
     * @param int $sourceId
     * @return array<string, mixed>
     */
    private function buildRequestFromSource(array $source, $sourceId)
    {
        $sourceType = isset($source['source_type']) ? strtolower(trim((string) $source['source_type'])) : '';
        $parserProfile = isset($source['parser_profile']) ? trim((string) $source['parser_profile']) : '';
        $feedUrl = isset($source['feed_url']) ? trim((string) $source['feed_url']) : '';

        return array(
            'source_id' => $sourceId,
            'source_key' => isset($source['slug']) ? (string) $source['slug'] : (string) $sourceId,
            'url' => $feedUrl,
            'allowed_domains' => $this->decodeAllowedDomains(
                isset($source['allowed_domains']) ? (string) $source['allowed_domains'] : ''
            ),
            'source_type' => $sourceType,
            'parser_profile' => $parserProfile,
        );
    }

    /**
     * @return array<int, string>
     */
    private function decodeAllowedDomains($jsonValue)
    {
        if (!is_string($jsonValue) || $jsonValue === '') {
            return array();
        }

        $decoded = json_decode($jsonValue, true);

        if (!is_array($decoded)) {
            return array();
        }

        $domains = array();

        foreach ($decoded as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $normalized = strtolower(trim($domain));

            if ($normalized !== '') {
                $domains[] = $normalized;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @param string $errorCode
     * @return AcquisitionResult
     */
    private function preAcquireError($errorCode)
    {
        return new AcquisitionResult(array(
            'success' => false,
            'warnings' => array(),
            'errors' => array((string) $errorCode),
            'error_code' => (string) $errorCode,
        ));
    }
}
