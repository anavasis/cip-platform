<?php

namespace App\Modules\Announcement\Domain;

/**
 * Full-item announcement extraction from acquired feed or HTML bodies.
 */
final class AnnouncementItemExtractor
{
    private const MAX_BODY_BYTES = 2097152;

    private const MAX_BODY_FIELD_CHARS = 65536;

    private const MAX_ITEM_NODES = 5000;

    private const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    private const CONTENT_NAMESPACE = 'http://purl.org/rss/1.0/modules/content/';

    /**
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    public function extract(
        string $body,
        string $sourceId,
        string $sourceType = '',
        string $parserProfile = '',
    ): array {
        $id = trim($sourceId);

        if ($id === '') {
            return $this->failure('invalid_source_id');
        }

        if ($body === '') {
            return $this->failure('empty_body');
        }

        if (str_contains($body, "\0")) {
            return $this->failure('invalid_content');
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return $this->failure('body_too_large');
        }

        $type = strtolower(trim($sourceType));
        $profile = trim($parserProfile);

        if ($type === 'html' || $profile === 'asep_announcements_v1') {
            return $this->extractHtmlAnnouncements($body, $id);
        }

        $dtdError = $this->rejectPrologDtdDeclarations($body);

        if ($dtdError !== '') {
            return $this->failure($dtdError);
        }

        if ($this->hasRecognizedFeedStart($body)) {
            return $this->extractFeed($body, $id);
        }

        return $this->failure('unrecognized_content');
    }

    /**
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractFeed(string $content, string $sourceId): array
    {
        if (! class_exists(\DOMDocument::class) || ! class_exists(\DOMXPath::class)) {
            return $this->failure('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $loadFlags = LIBXML_NONET;

        if (defined('LIBXML_COMPACT')) {
            $loadFlags |= LIBXML_COMPACT;
        }

        $loaded = $document->loadXML($content, $loadFlags);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        if ($loaded !== true) {
            return $this->failure('xml_parse_failed');
        }

        $root = $document->documentElement;

        if (! $root instanceof \DOMElement) {
            return $this->failure('xml_parse_failed');
        }

        $rootName = strtolower($root->localName !== '' ? $root->localName : $root->nodeName);

        if ($rootName === 'rss') {
            return $this->extractRss($document, $sourceId);
        }

        if ($rootName === 'feed') {
            return $this->extractAtom($document, $root, $sourceId);
        }

        return $this->failure('unrecognized_root');
    }

    /**
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractRss(\DOMDocument $document, string $sourceId): array
    {
        $itemNodes = $document->getElementsByTagName('item');

        if ($itemNodes->length > self::MAX_ITEM_NODES) {
            return $this->failure('structure_too_large');
        }

        $candidates = [];

        for ($index = 0; $index < $itemNodes->length; $index++) {
            $item = $itemNodes->item($index);

            if (! $item instanceof \DOMElement) {
                continue;
            }

            $title = $this->readFirstChildText($item, 'title');
            $link = $this->readRssItemLink($item);
            $guid = $this->readFirstChildText($item, 'guid');
            $date = $this->readFirstChildText($item, 'pubDate');
            $canonical = $link !== '' ? $link : $guid;

            if ($canonical === '') {
                continue;
            }

            $rawPayload = [
                'schema_version' => 1,
                'intake_method' => 'editorial_spine_rss',
                'title' => $title,
                'link' => $link,
                'guid' => $guid,
                'pubDate' => $date,
            ];
            $this->appendBodyField($rawPayload, 'description', $this->readFirstChildText($item, 'description'));
            $this->appendBodyField($rawPayload, 'content', $this->readRssContentEncoded($item));

            $candidates[] = new AnnouncementCandidate([
                'source_id' => $sourceId,
                'title' => $title,
                'canonical_url' => $canonical,
                'source_guid' => $guid,
                'published_at_utc' => $this->normalizeDate($date),
                'raw_payload' => $rawPayload,
            ]);
        }

        return [
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractAtom(
        \DOMDocument $document,
        \DOMElement $root,
        string $sourceId,
    ): array {
        $entries = $this->collectAtomEntries($document, $root);

        if (count($entries) > self::MAX_ITEM_NODES) {
            return $this->failure('structure_too_large');
        }

        $candidates = [];

        foreach ($entries as $entry) {
            $title = $this->readAtomText($entry, 'title');
            $link = $this->readAtomLink($entry);
            $guid = $this->readAtomText($entry, 'id');
            $date = $this->readAtomDate($entry);
            $canonical = $link !== '' ? $link : $guid;

            if ($canonical === '') {
                continue;
            }

            $rawPayload = [
                'schema_version' => 1,
                'intake_method' => 'editorial_spine_atom',
                'title' => $title,
                'link' => $link,
                'id' => $guid,
                'date' => $date,
            ];
            $this->appendBodyField($rawPayload, 'summary', $this->readAtomText($entry, 'summary'));
            $this->appendBodyField($rawPayload, 'content', $this->readAtomText($entry, 'content'));

            $candidates[] = new AnnouncementCandidate([
                'source_id' => $sourceId,
                'title' => $title,
                'canonical_url' => $canonical,
                'source_guid' => $guid,
                'published_at_utc' => $this->normalizeDate($date),
                'raw_payload' => $rawPayload,
            ]);
        }

        return [
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        ];
    }

    /**
     * Bounded HTML announcement extraction for ASEP-style listing markup.
     *
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractHtmlAnnouncements(string $content, string $sourceId): array
    {
        if (! class_exists(\DOMDocument::class) || ! class_exists(\DOMXPath::class)) {
            return $this->failure('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $loadFlags = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

        if (defined('LIBXML_COMPACT')) {
            $loadFlags |= LIBXML_COMPACT;
        }

        $loaded = $document->loadHTML($content, $loadFlags);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        if ($loaded !== true) {
            return $this->failure('parse_failed');
        }

        $xpath = new \DOMXPath($document);
        $anchors = $xpath->query('//a[@href]');
        $candidates = [];
        $seen = [];

        if ($anchors instanceof \DOMNodeList) {
            foreach ($anchors as $anchor) {
                if (! $anchor instanceof \DOMElement) {
                    continue;
                }

                if (count($candidates) >= self::MAX_ITEM_NODES) {
                    break;
                }

                $href = trim($anchor->getAttribute('href'));
                $title = trim($anchor->textContent);

                if ($href === '' || $title === '') {
                    continue;
                }

                $key = strtolower($href);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $candidates[] = new AnnouncementCandidate([
                    'source_id' => $sourceId,
                    'title' => $title,
                    'canonical_url' => $href,
                    'source_guid' => '',
                    'published_at_utc' => '',
                    'raw_payload' => [
                        'schema_version' => 1,
                        'intake_method' => 'editorial_spine_html',
                        'title' => $title,
                        'href' => $href,
                    ],
                ]);
            }
        }

        return [
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function collectAtomEntries(\DOMDocument $document, \DOMElement $root): array
    {
        $entries = [];
        $xpath = new \DOMXPath($document);
        $query = '//*[local-name()="entry" and (namespace-uri()="" or namespace-uri()="'
            .self::ATOM_NAMESPACE
            .'")]';
        $nodes = $xpath->query($query, $root);

        if ($nodes instanceof \DOMNodeList) {
            foreach ($nodes as $node) {
                if ($node instanceof \DOMElement) {
                    $entries[] = $node;
                }
            }
        }

        return $entries;
    }

    private function hasRecognizedFeedStart(string $content): bool
    {
        $trimmed = ltrim($content);

        if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
            $trimmed = ltrim(substr($trimmed, 3));
        }

        return preg_match('/^(<\?xml[^>]*>\s*)?<(rss|feed)\b/i', $trimmed) === 1;
    }

    /**
     * Reject DTD/entity declarations only in the XML prolog (before the feed root).
     * Literal <!DOCTYPE / <!ENTITY strings inside CDATA after <rss>/<feed> are allowed.
     */
    private function rejectPrologDtdDeclarations(string $body): string
    {
        $trimmed = ltrim($body);

        if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
            $trimmed = ltrim(substr($trimmed, 3));
        }

        $prolog = $trimmed;

        if (preg_match('/<(rss|feed)\b/i', $trimmed, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $prolog = substr($trimmed, 0, $matches[0][1]);
        }

        if (stripos($prolog, '<!DOCTYPE') !== false) {
            return 'doctype_not_allowed';
        }

        if (stripos($prolog, '<!ENTITY') !== false) {
            return 'entity_not_allowed';
        }

        return '';
    }

    private function readRssContentEncoded(\DOMElement $item): string
    {
        $nodes = $item->getElementsByTagNameNS(self::CONTENT_NAMESPACE, 'encoded');

        if ($nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMNode ? trim($node->textContent) : '';
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function appendBodyField(array &$rawPayload, string $key, string $value): void
    {
        $bounded = $this->boundBodyField($value);

        if ($bounded !== '') {
            $rawPayload[$key] = $bounded;
        }
    }

    private function boundBodyField(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, self::MAX_BODY_FIELD_CHARS, 'UTF-8');
        }

        if (strlen($trimmed) <= self::MAX_BODY_FIELD_CHARS) {
            return $trimmed;
        }

        return substr($trimmed, 0, self::MAX_BODY_FIELD_CHARS);
    }

    private function readRssItemLink(\DOMElement $item): string
    {
        $link = $this->readFirstChildText($item, 'link');

        if ($link !== '') {
            return $link;
        }

        return $this->readFirstChildText($item, 'guid');
    }

    private function readFirstChildText(\DOMElement $parent, string $tagName): string
    {
        $nodes = $parent->getElementsByTagName($tagName);

        if ($nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMNode ? trim($node->textContent) : '';
    }

    private function readAtomText(\DOMElement $entry, string $localName): string
    {
        foreach ($entry->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && strtolower($child->localName !== '' ? $child->localName : $child->nodeName) === strtolower($localName)
            ) {
                return trim($child->textContent);
            }
        }

        return '';
    }

    private function readAtomLink(\DOMElement $entry): string
    {
        foreach ($entry->childNodes as $child) {
            if (
                ! $child instanceof \DOMElement
                || strtolower($child->localName !== '' ? $child->localName : $child->nodeName) !== 'link'
            ) {
                continue;
            }

            $rel = strtolower(trim($child->getAttribute('rel')));

            if ($rel === '' || $rel === 'alternate') {
                $href = trim($child->getAttribute('href'));

                if ($href !== '') {
                    return $href;
                }
            }
        }

        return '';
    }

    private function readAtomDate(\DOMElement $entry): string
    {
        $updated = $this->readAtomText($entry, 'updated');

        if ($updated !== '') {
            return $updated;
        }

        return $this->readAtomText($entry, 'published');
    }

    private function normalizeDate(string $rawDate): string
    {
        $trimmed = trim($rawDate);

        if ($trimmed === '') {
            return '';
        }

        $timestamp = strtotime($trimmed);

        if ($timestamp === false) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function failure(string $errorCode): array
    {
        return [
            'success' => false,
            'error_code' => $errorCode,
            'candidates' => [],
        ];
    }
}
