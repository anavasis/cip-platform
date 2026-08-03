<?php

namespace App\Modules\Acquisition\Domain\Feed;

final class AsepAnnouncementsHtmlParser
{
    public const SUPPORTED_PROFILE = 'asep_announcements_v1';

    private const MAX_BODY_BYTES = 2097152;

    private const MAX_CANDIDATE_ROWS = 250;

    private const MAX_PREVIEW_ITEMS = 5;

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, mixed>
     */
    public function parse(
        string $body,
        string $contentType,
        string $parserProfile,
        string $finalUrl,
        array $allowedDomains,
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

        if (str_contains($body, "\0")) {
            return $this->errorResult('invalid_html_start');
        }

        if (stripos($body, '<!ENTITY') !== false) {
            return $this->errorResult('entity_not_allowed');
        }

        $startError = $this->validateDocumentStart($body);

        if ($startError !== '') {
            return $this->errorResult($startError);
        }

        if (! class_exists(\DOMDocument::class) || ! class_exists(\DOMXPath::class)) {
            return $this->errorResult('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
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
            ."/div[contains(concat(' ', normalize-space(@class), ' '), ' view-content ')]";
        $containers = $xpath->query($containerQuery);

        if (! $containers instanceof \DOMNodeList || $containers->length !== 1) {
            return $this->errorResult('structure_not_found');
        }

        $container = $containers->item(0);

        if (! $container instanceof \DOMElement) {
            return $this->errorResult('structure_not_found');
        }

        $rowNodes = $xpath->query(
            ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-row ')]",
            $container,
        );

        if (! $rowNodes instanceof \DOMNodeList) {
            return $this->errorResult('structure_not_found');
        }

        if ($rowNodes->length > self::MAX_CANDIDATE_ROWS) {
            return $this->errorResult('structure_too_large');
        }

        $acceptedCount = 0;
        $previewItems = [];

        foreach ($rowNodes as $rowNode) {
            if (! $rowNode instanceof \DOMElement || $this->isRowHidden($rowNode)) {
                continue;
            }

            $acceptedCount++;

            if (count($previewItems) < self::MAX_PREVIEW_ITEMS) {
                $previewItems[] = $this->extractRow($xpath, $rowNode, $finalUrl, $allowedDomains);
            }
        }

        return [
            'success' => true,
            'error_code' => '',
            'format' => 'html',
            'item_count' => $acceptedCount,
            'preview_items' => $previewItems,
        ];
    }

    private function normalizeContentType(string $contentType): string
    {
        return trim(strtolower(strtok(trim($contentType), ';')));
    }

    private function validateDocumentStart(string $body): string
    {
        $trimmed = strncmp($body, "\xEF\xBB\xBF", 3) === 0 ? substr($body, 3) : $body;
        $trimmed = ltrim($trimmed, " \t\r\n");
        $doctypeOccurrences = preg_match_all('/<!DOCTYPE/i', $body);

        if ($doctypeOccurrences > 1) {
            return 'doctype_not_allowed';
        }

        if (stripos($trimmed, '<!doctype') === 0) {
            if ($doctypeOccurrences !== 1 || preg_match('/^<!DOCTYPE\s+html\s*>/i', $trimmed, $matches) !== 1) {
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
        foreach (['script', 'style', 'noscript', 'form'] as $tagName) {
            $nodesToRemove = [];

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
        return $row->hasAttribute('hidden')
            || strtolower(trim($row->getAttribute('aria-hidden'))) === 'true'
            || preg_match('/display\s*:\s*none/i', $row->getAttribute('style')) === 1;
    }

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, string>
     */
    private function extractRow(
        \DOMXPath $xpath,
        \DOMElement $row,
        string $finalUrl,
        array $allowedDomains,
    ): array {
        return [
            'title' => $this->extractTitle($xpath, $row),
            'link' => $this->extractLink($xpath, $row, $finalUrl, $allowedDomains),
            'date' => $this->extractDate($xpath, $row),
            'category' => $this->extractCategory($xpath, $row),
        ];
    }

    private function extractCategory(\DOMXPath $xpath, \DOMElement $row): string
    {
        return $this->firstNodeText(
            $xpath,
            $row,
            ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-field-announcement-type ')]"
                ."//div[contains(concat(' ', normalize-space(@class), ' '), ' field-content ')]",
        );
    }

    private function extractTitle(\DOMXPath $xpath, \DOMElement $row): string
    {
        return $this->firstNodeText(
            $xpath,
            $row,
            ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-title ')]"
                ."//h3[contains(concat(' ', normalize-space(@class), ' '), ' field-content ')]",
        );
    }

    private function extractDate(\DOMXPath $xpath, \DOMElement $row): string
    {
        $nodes = $xpath->query(
            ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-field-issue-date ')]//time",
            $row,
        );
        $node = $nodes instanceof \DOMNodeList ? $nodes->item(0) : null;

        if (! $node instanceof \DOMElement) {
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

    /** @param array<int, string> $allowedDomains */
    private function extractLink(
        \DOMXPath $xpath,
        \DOMElement $row,
        string $finalUrl,
        array $allowedDomains,
    ): string {
        $nodes = $xpath->query(
            ".//div[contains(concat(' ', normalize-space(@class), ' '), ' views-field-view-node ')]//a[@href]",
            $row,
        );
        $anchor = $nodes instanceof \DOMNodeList ? $nodes->item(0) : null;

        if (! $anchor instanceof \DOMElement) {
            return '';
        }

        return $this->resolveAndValidateLink(
            trim($anchor->getAttribute('href')),
            $finalUrl,
            $allowedDomains,
        );
    }

    private function firstNodeText(\DOMXPath $xpath, \DOMElement $context, string $query): string
    {
        $nodes = $xpath->query($query, $context);
        $node = $nodes instanceof \DOMNodeList ? $nodes->item(0) : null;

        return $node instanceof \DOMNode ? $this->normalizeText($node->textContent) : '';
    }

    private function normalizeText(string $text): string
    {
        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $text);

        return is_string($collapsed) ? trim($collapsed) : '';
    }

    /** @param array<int, string> $allowedDomains */
    private function resolveAndValidateLink(string $href, string $finalUrl, array $allowedDomains): string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }

        if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $href) === 1
            && preg_match('#^https?://#i', $href) !== 1) {
            return '';
        }

        $resolved = $this->resolveUrl($finalUrl, $href);

        if ($resolved === '') {
            return '';
        }

        $parsed = $this->parseResolvedUrl($resolved);

        if ($parsed === null || $parsed['fragment'] !== ''
            || ! $this->hostAllowed($parsed['host'], $allowedDomains)) {
            return '';
        }

        return $parsed['url'];
    }

    private function resolveUrl(string $base, string $href): string
    {
        $baseParts = parse_url($base);
        $hrefParts = parse_url($href);

        if (! is_array($baseParts) || ! isset($baseParts['scheme'], $baseParts['host'])
            || ! is_array($hrefParts) || isset($hrefParts['user']) || isset($hrefParts['pass'])) {
            return '';
        }

        $scheme = isset($hrefParts['scheme'])
            ? strtolower((string) $hrefParts['scheme'])
            : strtolower((string) $baseParts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return '';
        }

        $hasHrefHost = isset($hrefParts['host']) && $hrefParts['host'] !== '';
        $host = $hasHrefHost ? (string) $hrefParts['host'] : (string) $baseParts['host'];
        $portParts = $hasHrefHost ? $hrefParts : $baseParts;
        $port = isset($portParts['port']) ? ':'.(int) $portParts['port'] : '';
        $path = isset($hrefParts['path']) ? (string) $hrefParts['path'] : '';

        if ($path === '') {
            $path = $hasHrefHost
                ? '/'
                : (isset($baseParts['path']) && $baseParts['path'] !== '' ? (string) $baseParts['path'] : '/');
        } elseif (! str_starts_with($path, '/') && ! $hasHrefHost) {
            $basePath = isset($baseParts['path']) && $baseParts['path'] !== ''
                ? (string) $baseParts['path']
                : '/';
            $lastSlashPosition = strrpos($basePath, '/');
            $baseDir = $lastSlashPosition !== false ? substr($basePath, 0, $lastSlashPosition + 1) : '/';
            $path = $baseDir.$path;
        }

        $query = isset($hrefParts['query']) ? '?'.$hrefParts['query'] : '';
        $fragment = isset($hrefParts['fragment']) ? '#'.$hrefParts['fragment'] : '';

        return $scheme.'://'.$host.$port.$this->normalizePathSegments($path).$query.$fragment;
    }

    private function normalizePathSegments(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $output = [];

        foreach (explode('/', $path) as $segment) {
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

    /** @return array{url: string, host: string, fragment: string}|null */
    private function parseResolvedUrl(string $url): ?array
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])
            || isset($parsed['user']) || isset($parsed['pass'])
            || ! in_array(strtolower((string) $parsed['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = trim((string) $parsed['host']);

        if ($host === '') {
            return null;
        }

        return [
            'url' => $url,
            'host' => $host,
            'fragment' => isset($parsed['fragment']) ? (string) $parsed['fragment'] : '',
        ];
    }

    /** @param array<int, string> $allowedDomains */
    private function hostAllowed(string $host, array $allowedDomains): bool
    {
        $normalizedHost = $this->normalizeHostForComparison($host);

        if ($normalizedHost === '') {
            return false;
        }

        foreach ($allowedDomains as $allowedDomain) {
            if (is_string($allowedDomain)
                && $normalizedHost === $this->normalizeHostForComparison($allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHostForComparison(string $host): string
    {
        $value = rtrim(strtolower(trim($host)), '.');

        if ($value === '') {
            return '';
        }

        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            if (! function_exists('idn_to_ascii')) {
                return '';
            }

            $ascii = defined('INTL_IDNA_VARIANT_UTS46')
                ? idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : idn_to_ascii($value);

            if (! is_string($ascii) || $ascii === '') {
                return '';
            }

            $value = strtolower($ascii);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function errorResult(string $errorCode): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'format' => '',
            'item_count' => 0,
            'preview_items' => [],
        ];
    }
}
