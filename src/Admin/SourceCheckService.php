<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;

defined('ABSPATH') || exit;

final class SourceCheckService
{
    private $sourceAcquisitionService;

    public function __construct(SourceAcquisitionService $sourceAcquisitionService)
    {
        $this->sourceAcquisitionService = $sourceAcquisitionService;
    }

    /**
     * @return array<string, mixed>
     */
    public function check($sourceId)
    {
        $acquisition = $this->sourceAcquisitionService->acquireFromSource($sourceId);

        $fetchResult = $acquisition->fetchResult();
        $parseResult = $acquisition->parseResult();

        if ($acquisition->success() !== true) {
            $errorCode = $acquisition->errorCode();

            if (
                $fetchResult !== array()
                && isset($fetchResult['success'])
                && $fetchResult['success'] === true
                && $parseResult !== array()
            ) {
                $mapped = $this->mapParserErrorCode($errorCode);

                return array(
                    'success' => false,
                    'error_code' => $mapped,
                    'error_message' => $this->messageForCode($mapped),
                    'requested_url' => (string) $fetchResult['requested_url'],
                    'final_url' => (string) $fetchResult['final_url'],
                    'http_status' => (int) $fetchResult['http_status'],
                    'content_type' => (string) $fetchResult['content_type'],
                    'response_size' => (int) $fetchResult['response_size'],
                    'format' => '',
                    'item_count' => 0,
                    'preview_items' => array(),
                );
            }

            if (
                $fetchResult !== array()
                && (
                    !isset($fetchResult['success'])
                    || $fetchResult['success'] !== true
                )
            ) {
                return $this->buildFetchError($fetchResult);
            }

            return $this->buildError($errorCode !== '' ? $errorCode : 'transport_error');
        }

        return array(
            'success' => true,
            'error_code' => '',
            'error_message' => '',
            'requested_url' => (string) $fetchResult['requested_url'],
            'final_url' => (string) $fetchResult['final_url'],
            'http_status' => (int) $fetchResult['http_status'],
            'content_type' => (string) $fetchResult['content_type'],
            'response_size' => (int) $fetchResult['response_size'],
            'format' => (string) $parseResult['format'],
            'item_count' => (int) $parseResult['item_count'],
            'preview_items' => is_array($parseResult['preview_items'])
                ? $parseResult['preview_items']
                : array(),
        );
    }

    /**
     * @param array<string, mixed> $fetchResult
     * @return array<string, mixed>
     */
    private function buildFetchError(array $fetchResult)
    {
        $errorCode = isset($fetchResult['error_code'])
            ? (string) $fetchResult['error_code']
            : 'transport_error';

        return array(
            'success' => false,
            'error_code' => $errorCode,
            'error_message' => $this->messageForCode($errorCode),
            'requested_url' => isset($fetchResult['requested_url']) ? (string) $fetchResult['requested_url'] : '',
            'final_url' => isset($fetchResult['final_url']) ? (string) $fetchResult['final_url'] : '',
            'http_status' => isset($fetchResult['http_status']) ? (int) $fetchResult['http_status'] : 0,
            'content_type' => isset($fetchResult['content_type']) ? (string) $fetchResult['content_type'] : '',
            'response_size' => isset($fetchResult['response_size']) ? (int) $fetchResult['response_size'] : 0,
            'format' => '',
            'item_count' => 0,
            'preview_items' => array(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildError($errorCode)
    {
        return array(
            'success' => false,
            'error_code' => (string) $errorCode,
            'error_message' => $this->messageForCode($errorCode),
            'requested_url' => '',
            'final_url' => '',
            'http_status' => 0,
            'content_type' => '',
            'response_size' => 0,
            'format' => '',
            'item_count' => 0,
            'preview_items' => array(),
        );
    }

    private function mapParserErrorCode($parserCode)
    {
        $code = (string) $parserCode;

        if ($code === 'body_too_large' || $code === 'structure_too_large' || $code === 'response_too_large') {
            return 'response_too_large';
        }

        if (
            $code === 'empty_body'
            || $code === 'invalid_content'
            || $code === 'doctype_not_allowed'
            || $code === 'entity_not_allowed'
            || $code === 'unexpected_content_type'
            || $code === 'invalid_html_start'
            || $code === 'structure_not_found'
        ) {
            return 'invalid_feed_content';
        }

        if ($code === 'unrecognized_feed' || $code === 'unrecognized_root' || $code === 'xml_parse_failed') {
            return 'unrecognized_feed';
        }

        return 'invalid_feed_content';
    }

    private function messageForCode($code)
    {
        $messages = array(
            'invalid_id' => 'The requested source identifier is invalid.',
            'not_found' => 'The requested source could not be found.',
            'missing_feed_url' => 'This source does not have a feed URL configured.',
            'allowed_domains_invalid' => 'Allowed domains are missing or invalid for this source.',
            'unsupported_source_type' => 'This source type is not supported for checking.',
            'unsupported_parser_profile' => 'This source does not have a supported parser profile configured.',
            'url_blocked' => 'The feed URL failed safety validation.',
            'redirect_blocked' => 'A redirect target failed safety validation.',
            'invalid_redirect' => 'The remote server returned an invalid redirect.',
            'too_many_redirects' => 'The remote server redirected too many times.',
            'transport_error' => 'The feed could not be retrieved.',
            'http_error' => 'The remote server returned an error response.',
            'response_too_large' => 'The feed response exceeded the allowed size.',
            'invalid_feed_content' => 'The feed content could not be accepted safely.',
            'unrecognized_feed' => 'The response was not recognized as RSS or Atom.',
        );

        return isset($messages[$code]) ? $messages[$code] : 'The source check could not be completed.';
    }
}
