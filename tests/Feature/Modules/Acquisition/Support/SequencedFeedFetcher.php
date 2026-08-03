<?php

namespace Tests\Feature\Modules\Acquisition\Support;

use App\Modules\Acquisition\Infrastructure\Http\FeedFetcherInterface;

/**
 * Test-only feed fetcher sequence used by acquisition feature tests that do not
 * exercise the real cURL transport (see CurlPinnedTransportIntegrationTest).
 */
final class SequencedFeedFetcher implements FeedFetcherInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $responses;

    private int $index = 0;

    /** @param array<int, array<string, mixed>> $responses */
    public function __construct(array $responses)
    {
        $this->responses = array_values($responses);
    }

    public function fetch(string $url, array $allowedDomains): array
    {
        return $this->next($url);
    }

    public function fetchForConnectivityAudit(string $url, array $allowedDomains): array
    {
        $result = $this->next($url);

        if (($result['success'] ?? false) === true) {
            return [
                'success' => true,
                'result_code' => '',
                'http_status' => (int) ($result['http_status'] ?? 200),
                'content_type' => (string) ($result['content_type'] ?? ''),
                'response_bytes' => (int) ($result['response_size'] ?? strlen((string) ($result['body'] ?? ''))),
                'elapsed_ms' => 1,
                'redirect_count' => 0,
                'truncated' => false,
                'body' => (string) ($result['body'] ?? ''),
            ];
        }

        return [
            'success' => false,
            'result_code' => (string) ($result['error_code'] ?? 'network_error'),
            'http_status' => (int) ($result['http_status'] ?? 0),
            'content_type' => (string) ($result['content_type'] ?? ''),
            'response_bytes' => (int) ($result['response_size'] ?? 0),
            'elapsed_ms' => 1,
            'redirect_count' => 0,
            'truncated' => false,
            'body' => '',
        ];
    }

    public function sentCount(): int
    {
        return $this->index;
    }

    /** @return array<string, mixed> */
    private function next(string $url): array
    {
        if (! isset($this->responses[$this->index])) {
            return [
                'success' => false,
                'error_code' => 'transport_error',
                'requested_url' => $url,
                'final_url' => $url,
                'http_status' => 0,
                'content_type' => '',
                'response_size' => 0,
                'body' => '',
            ];
        }

        $response = $this->responses[$this->index];
        $this->index++;

        return array_merge([
            'requested_url' => $url,
            'final_url' => $url,
            'http_status' => 200,
            'content_type' => 'application/rss+xml',
            'response_size' => strlen((string) ($response['body'] ?? '')),
            'body' => '',
            'error_code' => '',
            'success' => true,
        ], $response);
    }
}
