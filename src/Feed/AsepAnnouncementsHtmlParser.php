<?php

namespace StudyMentor\ContentEngine\Feed;

defined('ABSPATH') || exit;

final class AsepAnnouncementsHtmlParser
{
    public const SUPPORTED_PROFILE = 'asep_announcements_v1';

    private const MAX_BODY_BYTES = 2097152;
    private const MAX_CANDIDATE_ROWS = 250;
    private const MAX_PREVIEW_ITEMS = 5;

    /**
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function parse(
        string $body,
        string $contentType,
        string $parserProfile,
        string $finalUrl,
        array $allowedDomains
    ): array {
        if ($parserProfile !== self::SUPPORTED_PROFILE) {
            return $this->errorResult('unsupported_parser_profile');
        }

        if ($this->normalizeContentType($contentType) !== 'text/html') {
            return $this->errorResult('unexpected_content_type');
        }

        if ($body === '') {
            return $this->errorResult('empty_body');
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return $this->errorResult('response_too_large');
        }

        if (strpos($body, "\0") !== false) {
            return $this->errorResult('invalid_html_start');
        }

        if (stripos($body, '<!ENTITY') !== false) {
            return $this->errorResult('entity_not_allowed');
        }

        $startError = $this->validateDocumentStart($body);

        if ($startError !== '') {
            return $this->errorResult($startError);
        }

        if (!class_exists('\DOMDocument') || !class_exists('\DOMXPath')) {
            return $this->errorResult('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loadFlags = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

        if (defined('LIBXML_COMPACT')) {
            $loadFlags |= LIBXML_COMPACT;
        }

        $loaded = $document->loadHTML($body, $loadFlags);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        if ($loaded !== true) {
            return $this->errorResult('parse_failed');
        }

        $this->removeDisallowedElements($document);

        $xpath = new \DOMXPath($document);
        $containerQuery = "//div[contains(concat(' ', normalize-space(@class), ' '), ' view-id-contest_announcements_view ')]"
            . "/div[contains(concat(' ', normalize-space(@class), ' '), ' view-content ')]";
        $containers = $xpath->query($containerQuery);

        if (!$containers instanceof \DOMNodeList || $containers->length !== 1) {
            return $this->errorResult('structure_not_found');
        }

        $container = $containers->item(0);

        if (!$container instanceof \DOMElement) {
            return $this->errorResult('structure_not_found');
        }

        $rowQuery = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-row ')]";
        $rowNodes = $xpath->query($rowQuery, $container);

        if (!$rowNodes instanceof \DOMNodeList) {
            return $this->errorResult('structure_not_found');
        }

        if ($rowNodes->length > self::MAX_CANDIDATE_ROWS) {
            return $this->errorResult('structure_too_large');
        }

        $acceptedCount = 0;
        $previewItems = array();

        foreach ($rowNodes as $rowNode) {
            if (!$rowNode instanceof \DOMElement || $this->isRowHidden($rowNode)) {
                continue;
            }

            $acceptedCount++;

            if (count($previewItems) < self::MAX_PREVIEW_ITEMS) {
                $previewItems[] = $this->extractRow($xpath, $rowNode, $finalUrl, $allowedDomains);
            }
        }

        return array(
            'success' => true,
            'error_code' => '',
            'format' => 'html',
            'item_count' => $acceptedCount,
            'preview_items' => $previewItems,
        );
    }

    private function normalizeContentType(string $contentType): string
    {
        $value = strtolower(trim($contentType));

        if ($value === '') {
            return '';
        }

        $semicolonPosition = strpos($value, ';');

        if ($semicolonPosition !== false) {
            $value = substr($value, 0, $semicolonPosition);
        }

        return trim($value);
    }

    /**
     * @return string Empty string when valid, otherwise an error code.
     */
    private function validateDocumentStart(string $body): string
    {
        $trimmed = $body;

        if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
            $trimmed = substr($trimmed, 3);
        }

        $trimmed = ltrim($trimmed, " \t\r\n");
        $doctypeOccurrences = preg_match_all('/<!DOCTYPE/i', $body);

        if ($doctypeOccurrences > 1) {
            return 'doctype_not_allowed';
        }

        if (stripos($trimmed, '<!doctype') === 0) {
            if ($doctypeOccurrences !== 1) {
                return 'doctype_not_allowed';
            }

            if (preg_match('/^<!DOCTYPE\s+html\s*>/i', $trimmed, $matches) !== 1) {
                return 'doctype_not_allowed';
            }

            $remainder = ltrim(substr($trimmed, strlen($matches[0])), " \t\r\n");

            return $this->startsWithHtmlTag($remainder) ? '' : 'invalid_html_start';
        }

        if ($doctypeOccurrences > 0) {
            return 'doctype_not_allowed';
        }

        return $this->startsWithHtmlTag($trimmed) ? '' : 'invalid_html_start';
    }

    private function startsWithHtmlTag(string $value): bool
    {
        return preg_match('/^<html(?=[\s>\/])/i', $value) === 1;
    }

    private function removeDisallowedElements(\DOMDocument $document): void
    {
        foreach (array('script', 'style', 'noscript', 'form') as $tagName) {
            $nodesToRemove = array();

            foreach ($document->getElementsByTagName($tagName) as $node) {
                $nodesToRemove[] = $node;
            }

            foreach ($nodesToRemove as $node) {
                if ($node->parentNode instanceof \DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    private function isRowHidden(\DOMElement $row): bool
    {
        if ($row->hasAttribute('hidden')) {
            return true;
        }

        if (strtolower(trim($row->getAttribute('aria-hidden'))) === 'true') {
            return true;
        }

        if (preg_match('/display\s*:\s*none/i', $row->getAttribute('style')) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, string> $allowedDomains
     * @return array<string, string>
     */
    private function extractRow(\DOMXPath $xpath, \DOMElement $row, string $finalUrl, array $allowedDomains): array
    {
        return array(
            'title' => $this->extractTitle($xpath, $row),
            'link' => $this->extractLink($xpath, $row, $finalUrl, $allowedDomains),
            'date' => $this->extractDate($xpath, $row),
            'category' => $this->extractCategory($xpath, $row),
        );
    }

    private function extractCategory(\DOMXPath $xpath, \DOMElement $row): string
    {
        $query = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-field-announcement-type ')]"
            . "//div[contains(concat(' ', normalize-space(@class), ' '), ' field-content ')]";

        return $this->firstNodeText($xpath, $row, $query);
    }

    private function extractTitle(\DOMXPath $xpath, \DOMElement $row): string
    {
        $query = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-title ')]"
            . "//h3[contains(concat(' ', normalize-space(@class), ' '), ' field-content ')]";

        return $this->firstNodeText($xpath, $row, $query);
    }

    private function extractDate(\DOMXPath $xpath, \DOMElement $row): string
    {
        $query = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-field-issue-date ')]//time";
        $nodes = $xpath->query($query, $row);

        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        if (!$node instanceof \DOMElement) {
            return '';
        }

        if ($node->hasAttribute('datetime')) {
            $datetime = $this->normalizeText($node->getAttribute('datetime'));

            if ($datetime !== '') {
                return $datetime;
            }
        }

        return $this->normalizeText($node->textContent);
    }

    /**
     * @param array<int, string> $allowedDomains
     */
    private function extractLink(\DOMXPath $xpath, \DOMElement $row, string $finalUrl, array $allowedDomains): string
    {
        $query = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-view-node ')]//a[@href]";
        $nodes = $xpath->query($query, $row);

        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        $anchor = $nodes->item(0);

        if (!$anchor instanceof \DOMElement) {
            return '';
        }

        return $this->resolveAndValidateLink(trim($anchor->getAttribute('href')), $finalUrl, $allowedDomains);
    }

    private function firstNodeText(\DOMXPath $xpath, \DOMElement $context, string $query): string
    {
        $nodes = $xpath->query($query, $context);

        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMNode ? $this->normalizeText($node->textContent) : '';
    }

    private function normalizeText(string $text): string
    {
        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);

        return is_string($collapsed) ? trim($collapsed) : '';
    }

    /**
     * @param array<int, string> $allowedDomains
     */
    private function resolveAndValidateLink(string $href, string $finalUrl, array $allowedDomains): string
    {
        if ($href === '' || strpos($href, '#') === 0) {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $href) === 1 && preg_match('#^https?://#i', $href) !== 1) {
            return '';
        }

        $resolved = $this->resolveUrl($finalUrl, $href);

        if ($resolved === '') {
            return '';
        }

        $parsed = $this->parseResolvedUrl($resolved);

        if ($parsed === null || $parsed['fragment'] !== '') {
            return '';
        }

        if (!$this->hostAllowed($parsed['host'], $allowedDomains)) {
            return '';
        }

        return $parsed['url'];
    }

    private function resolveUrl(string $base, string $href): string
    {
        $baseParts = parse_url($base);

        if (!is_array($baseParts) || !isset($baseParts['scheme'], $baseParts['host'])) {
            return '';
        }

        $hrefParts = parse_url($href);

        if (!is_array($hrefParts)) {
            return '';
        }

        if (isset($hrefParts['user']) || isset($hrefParts['pass'])) {
            return '';
        }

        $scheme = strtolower((string) $baseParts['scheme']);

        if (isset($hrefParts['scheme'])) {
            $scheme = strtolower((string) $hrefParts['scheme']);

            if ($scheme !== 'http' && $scheme !== 'https') {
                return '';
            }
        }

        $hasHrefHost = isset($hrefParts['host']) && $hrefParts['host'] !== '';

        if ($hasHrefHost) {
            $host = (string) $hrefParts['host'];
            $port = isset($hrefParts['port']) ? ':' . (int) $hrefParts['port'] : '';
        } else {
            $host = (string) $baseParts['host'];
            $port = isset($baseParts['port']) ? ':' . (int) $baseParts['port'] : '';
        }

        $path = isset($hrefParts['path']) ? (string) $hrefParts['path'] : '';

        if ($path === '') {
            $path = $hasHrefHost ? '/' : (isset($baseParts['path']) && $baseParts['path'] !== '' ? (string) $baseParts['path'] : '/');
        } elseif (strpos($path, '/') !== 0 && !$hasHrefHost) {
            $basePath = isset($baseParts['path']) && $baseParts['path'] !== '' ? (string) $baseParts['path'] : '/';
            $lastSlashPosition = strrpos($basePath, '/');
            $baseDir = $lastSlashPosition !== false ? substr($basePath, 0, $lastSlashPosition + 1) : '/';
            $path = $baseDir . $path;
        }

        $normalizedPath = $this->normalizePathSegments($path);
        $query = isset($hrefParts['query']) ? '?' . $hrefParts['query'] : '';
        $fragment = isset($hrefParts['fragment']) ? '#' . $hrefParts['fragment'] : '';

        return $scheme . '://' . $host . $port . $normalizedPath . $query . $fragment;
    }

    private function normalizePathSegments(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $segments = explode('/', $path);
        $output = array();

        foreach ($segments as $segment) {
            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (count($output) > 1 || (count($output) === 1 && $output[0] !== '')) {
                    array_pop($output);
                }

                continue;
            }

            $output[] = $segment;
        }

        $normalized = implode('/', $output);

        return $normalized === '' ? '/' : $normalized;
    }

    /**
     * @return array{url: string, host: string, fragment: string}|null
     */
    private function parseResolvedUrl(string $url): ?array
    {
        $parsed = parse_url($url);

        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return null;
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = trim((string) $parsed['host']);

        if ($host === '') {
            return null;
        }

        return array(
            'url' => $url,
            'host' => $host,
            'fragment' => isset($parsed['fragment']) ? (string) $parsed['fragment'] : '',
        );
    }

    /**
     * @param array<int, string> $allowedDomains
     */
    private function hostAllowed(string $host, array $allowedDomains): bool
    {
        $normalizedHost = $this->normalizeHostForComparison($host);

        if ($normalizedHost === '') {
            return false;
        }

        foreach ($allowedDomains as $allowedDomain) {
            if (!is_string($allowedDomain)) {
                continue;
            }

            $normalizedAllowed = $this->normalizeHostForComparison($allowedDomain);

            if ($normalizedAllowed !== '' && $normalizedHost === $normalizedAllowed) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHostForComparison(string $host): string
    {
        $value = strtolower(trim($host));
        $value = rtrim($value, '.');

        if ($value === '') {
            return '';
        }

        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            if (!function_exists('idn_to_ascii')) {
                return '';
            }

            $ascii = defined('INTL_IDNA_VARIANT_UTS46')
                ? idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : idn_to_ascii($value);

            if ($ascii === false || !is_string($ascii) || $ascii === '') {
                return '';
            }

            $value = strtolower($ascii);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResult(string $errorCode): array
    {
        return array(
            'success' => false,
            'error_code' => $errorCode,
            'format' => '',
            'item_count' => 0,
            'preview_items' => array(),
        );
    }
}
