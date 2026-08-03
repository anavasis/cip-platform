<?php

namespace App\Modules\Acquisition\Domain\Feed;

final class FeedPreviewParser
{
    private const MAX_BODY_BYTES = 2097152;

    private const MAX_ITEM_NODES = 5000;

    private const MAX_PREVIEW_ITEMS = 5;

    private const ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    /** @return array<string, mixed> */
    public function parse(string $body): array
    {
        if ($body === '') {
            return $this->errorResult('empty_body');
        }

        if (str_contains($body, "\0")) {
            return $this->errorResult('invalid_content');
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return $this->errorResult('body_too_large');
        }

        if (stripos($body, '<!DOCTYPE') !== false) {
            return $this->errorResult('doctype_not_allowed');
        }

        if (stripos($body, '<!ENTITY') !== false) {
            return $this->errorResult('entity_not_allowed');
        }

        if (! $this->hasRecognizedFeedStart($body)) {
            return $this->errorResult('unrecognized_feed');
        }

        if (! class_exists(\DOMDocument::class) || ! class_exists(\DOMXPath::class)) {
            return $this->errorResult('parser_unavailable');
        }

        $previousLibxmlSetting = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $loadFlags = LIBXML_NONET;

        if (defined('LIBXML_COMPACT')) {
            $loadFlags |= LIBXML_COMPACT;
        }

        $loaded = $document->loadXML($body, $loadFlags);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        if ($loaded !== true) {
            return $this->errorResult('xml_parse_failed');
        }

        $root = $document->documentElement;

        if (! $root instanceof \DOMElement) {
            return $this->errorResult('xml_parse_failed');
        }

        $rootName = strtolower($root->localName !== '' ? $root->localName : $root->nodeName);

        return match ($rootName) {
            'rss' => $this->parseRss($document),
            'feed' => $this->parseAtom($document, $root),
            default => $this->errorResult('unrecognized_root'),
        };
    }

    private function hasRecognizedFeedStart(string $content): bool
    {
        $trimmed = ltrim($content);

        if (strncmp($trimmed, "\xEF\xBB\xBF", 3) === 0) {
            $trimmed = ltrim(substr($trimmed, 3));
        }

        return preg_match('/^(<\?xml[^>]*>\s*)?<(rss|feed)\b/i', $trimmed) === 1;
    }

    /** @return array<string, mixed> */
    private function parseRss(\DOMDocument $document): array
    {
        if ($document->getElementsByTagName('channel')->length === 0) {
            return $this->errorResult('unrecognized_root');
        }

        $itemNodes = $document->getElementsByTagName('item');

        if ($itemNodes->length > self::MAX_ITEM_NODES) {
            return $this->errorResult('structure_too_large');
        }

        $previewItems = [];
        $totalCount = $itemNodes->length;

        for ($index = 0; $index < $totalCount && count($previewItems) < self::MAX_PREVIEW_ITEMS; $index++) {
            $item = $itemNodes->item($index);

            if (! $item instanceof \DOMElement) {
                continue;
            }

            $previewItems[] = [
                'title' => $this->readFirstChildText($item, 'title'),
                'link' => $this->readRssItemLink($item),
                'date' => $this->readFirstChildText($item, 'pubDate'),
            ];
        }

        return [
            'success' => true,
            'error_code' => '',
            'format' => 'rss',
            'item_count' => $totalCount,
            'preview_items' => $previewItems,
        ];
    }

    /** @return array<string, mixed> */
    private function parseAtom(\DOMDocument $document, \DOMElement $root): array
    {
        $entryNodes = $this->collectAtomEntries($document, $root);

        if ($entryNodes === []) {
            return $this->errorResult('unrecognized_root');
        }

        if (count($entryNodes) > self::MAX_ITEM_NODES) {
            return $this->errorResult('structure_too_large');
        }

        $previewItems = [];
        $totalCount = count($entryNodes);

        foreach ($entryNodes as $entry) {
            if (count($previewItems) >= self::MAX_PREVIEW_ITEMS) {
                break;
            }

            $previewItems[] = [
                'title' => $this->readAtomText($entry, 'title'),
                'link' => $this->readAtomLink($entry),
                'date' => $this->readAtomDate($entry),
            ];
        }

        return [
            'success' => true,
            'error_code' => '',
            'format' => 'atom',
            'item_count' => $totalCount,
            'preview_items' => $previewItems,
        ];
    }

    /** @return array<int, \DOMElement> */
    private function collectAtomEntries(\DOMDocument $document, \DOMElement $root): array
    {
        $entries = [];
        $xpath = new \DOMXPath($document);
        $query = '//*[local-name()="entry" and (namespace-uri()="" or namespace-uri()="'.self::ATOM_NAMESPACE.'")]';
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

    private function readRssItemLink(\DOMElement $item): string
    {
        $link = $this->readFirstChildText($item, 'link');

        return $link !== '' ? $link : $this->readFirstChildText($item, 'guid');
    }

    private function readFirstChildText(\DOMElement $parent, string $tagName): string
    {
        $node = $parent->getElementsByTagName($tagName)->item(0);

        return $node instanceof \DOMNode ? trim($node->textContent) : '';
    }

    private function readAtomText(\DOMElement $entry, string $localName): string
    {
        $xpath = new \DOMXPath($entry->ownerDocument);
        $query = './*[local-name()="'.$localName.'" and (namespace-uri()="" or namespace-uri()="'.self::ATOM_NAMESPACE.'")]';
        $nodes = $xpath->query($query, $entry);

        if (! $nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMNode ? trim($node->textContent) : '';
    }

    private function readAtomLink(\DOMElement $entry): string
    {
        $xpath = new \DOMXPath($entry->ownerDocument);
        $query = './*[local-name()="link" and (namespace-uri()="" or namespace-uri()="'.self::ATOM_NAMESPACE.'")]';
        $nodes = $xpath->query($query, $entry);

        if (! $nodes instanceof \DOMNodeList) {
            return '';
        }

        $firstHref = '';

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement || ! $node->hasAttribute('href')) {
                continue;
            }

            $href = trim($node->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $firstHref = $firstHref === '' ? $href : $firstHref;
            $rel = strtolower(trim($node->getAttribute('rel')));

            if ($rel === '' || $rel === 'alternate') {
                return $href;
            }
        }

        return $firstHref;
    }

    private function readAtomDate(\DOMElement $entry): string
    {
        $published = $this->readAtomText($entry, 'published');

        return $published !== '' ? $published : $this->readAtomText($entry, 'updated');
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
