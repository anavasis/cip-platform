<?php

namespace StudyMentor\ContentEngine\Announcement;

defined('ABSPATH') || exit;

/**
 * Full-item announcement extraction from acquired feed/HTML bodies.
 * Independent of preview-limited Source Check parsers.
 */
final class AnnouncementItemExtractor
{
    private const MAX_BODY_BYTES = 2097152;
    private const MAX_ITEM_NODES = 5000;
    private const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    /**
     * @param string $body
     * @param int $sourceId
     * @param string $sourceType
     * @param string $parserProfile
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    public function extract($body, $sourceId, $sourceType = '', $parserProfile = '')
    {
        $content = (string) $body;
        $id = (int) $sourceId;

        if ($id <= 0) {
            return $this->failure('invalid_source_id');
        }

        if ($content === '') {
            return $this->failure('empty_body');
        }

        if (strpos($content, "\0") !== false) {
            return $this->failure('invalid_content');
        }

        if (strlen($content) > self::MAX_BODY_BYTES) {
            return $this->failure('body_too_large');
        }

        $type = strtolower(trim((string) $sourceType));
        $profile = trim((string) $parserProfile);

        if ($type === 'html' || $profile === 'asep_announcements_v1') {
            return $this->extractHtmlAnnouncements($content, $id);
        }

        if ($this->hasRecognizedFeedStart($content)) {
            return $this->extractFeed($content, $id);
        }

        return $this->failure('unrecognized_content');
    }

    /**
     * @param string $content
     * @param int $sourceId
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractFeed($content, $sourceId)
    {
        if (!class_exists('\DOMDocument') || !class_exists('\DOMXPath')) {
            return $this->failure('parser_unavailable');
        }

        if (stripos($content, '<!DOCTYPE') !== false) {
            return $this->failure('doctype_not_allowed');
        }

        if (stripos($content, '<!ENTITY') !== false) {
            return $this->failure('entity_not_allowed');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
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

        if (!$root instanceof \DOMElement) {
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
     * @param int $sourceId
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractRss(\DOMDocument $document, $sourceId)
    {
        $itemNodes = $document->getElementsByTagName('item');

        if ($itemNodes->length > self::MAX_ITEM_NODES) {
            return $this->failure('structure_too_large');
        }

        $candidates = array();

        for ($index = 0; $index < $itemNodes->length; $index++) {
            $item = $itemNodes->item($index);

            if (!$item instanceof \DOMElement) {
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

            $candidates[] = new AnnouncementCandidate(array(
                'source_id' => $sourceId,
                'title' => $title,
                'canonical_url' => $canonical,
                'source_guid' => $guid,
                'published_at_utc' => $this->normalizeDate($date),
                'raw_payload' => array(
                    'schema_version' => 1,
                    'intake_method' => 'editorial_spine_rss',
                    'title' => $title,
                    'link' => $link,
                    'guid' => $guid,
                    'pubDate' => $date,
                ),
            ));
        }

        return array(
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        );
    }

    /**
     * @param int $sourceId
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractAtom(\DOMDocument $document, \DOMElement $root, $sourceId)
    {
        $entries = $this->collectAtomEntries($document, $root);

        if (count($entries) > self::MAX_ITEM_NODES) {
            return $this->failure('structure_too_large');
        }

        $candidates = array();

        foreach ($entries as $entry) {
            $title = $this->readAtomText($entry, 'title');
            $link = $this->readAtomLink($entry);
            $guid = $this->readAtomText($entry, 'id');
            $date = $this->readAtomDate($entry);
            $canonical = $link !== '' ? $link : $guid;

            if ($canonical === '') {
                continue;
            }

            $candidates[] = new AnnouncementCandidate(array(
                'source_id' => $sourceId,
                'title' => $title,
                'canonical_url' => $canonical,
                'source_guid' => $guid,
                'published_at_utc' => $this->normalizeDate($date),
                'raw_payload' => array(
                    'schema_version' => 1,
                    'intake_method' => 'editorial_spine_atom',
                    'title' => $title,
                    'link' => $link,
                    'id' => $guid,
                    'date' => $date,
                ),
            ));
        }

        return array(
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        );
    }

    /**
     * Bounded HTML announcement extraction for ASEP-style listing markup.
     *
     * @param string $content
     * @param int $sourceId
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function extractHtmlAnnouncements($content, $sourceId)
    {
        if (!class_exists('\DOMDocument') || !class_exists('\DOMXPath')) {
            return $this->failure('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
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
        $candidates = array();
        $seen = array();

        if ($anchors instanceof \DOMNodeList) {
            foreach ($anchors as $anchor) {
                if (!$anchor instanceof \DOMElement) {
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
                $candidates[] = new AnnouncementCandidate(array(
                    'source_id' => $sourceId,
                    'title' => $title,
                    'canonical_url' => $href,
                    'source_guid' => '',
                    'published_at_utc' => '',
                    'raw_payload' => array(
                        'schema_version' => 1,
                        'intake_method' => 'editorial_spine_html',
                        'title' => $title,
                        'href' => $href,
                    ),
                ));
            }
        }

        return array(
            'success' => true,
            'error_code' => '',
            'candidates' => $candidates,
        );
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function collectAtomEntries(\DOMDocument $document, \DOMElement $root)
    {
        $entries = array();
        $xpath = new \DOMXPath($document);
        $query = '//*[local-name()="entry" and (namespace-uri()="" or namespace-uri()="'
            . self::ATOM_NAMESPACE
            . '")]';
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

    private function hasRecognizedFeedStart($content)
    {
        $trimmed = ltrim($content);

        if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
            $trimmed = ltrim(substr($trimmed, 3));
        }

        return preg_match('/^(<\?xml[^>]*>\s*)?<(rss|feed)\b/i', $trimmed) === 1;
    }

    private function readRssItemLink(\DOMElement $item)
    {
        $link = $this->readFirstChildText($item, 'link');

        if ($link !== '') {
            return $link;
        }

        return $this->readFirstChildText($item, 'guid');
    }

    private function readFirstChildText(\DOMElement $parent, $tagName)
    {
        $nodes = $parent->getElementsByTagName($tagName);

        if ($nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMNode ? trim($node->textContent) : '';
    }

    private function readAtomText(\DOMElement $entry, $localName)
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

    private function readAtomLink(\DOMElement $entry)
    {
        foreach ($entry->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
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

    private function readAtomDate(\DOMElement $entry)
    {
        $updated = $this->readAtomText($entry, 'updated');

        if ($updated !== '') {
            return $updated;
        }

        return $this->readAtomText($entry, 'published');
    }

    /**
     * @param string $rawDate
     * @return string
     */
    private function normalizeDate($rawDate)
    {
        $trimmed = trim((string) $rawDate);

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
     * @param string $errorCode
     * @return array{success: bool, error_code: string, candidates: array<int, AnnouncementCandidate>}
     */
    private function failure($errorCode)
    {
        return array(
            'success' => false,
            'error_code' => (string) $errorCode,
            'candidates' => array(),
        );
    }
}
