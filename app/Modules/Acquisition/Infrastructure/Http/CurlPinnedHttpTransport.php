<?php

namespace App\Modules\Acquisition\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Guarantees acquisition HTTP uses an explicitly constructed cURL handler.
 * Never falls back to StreamHandler. Fail-closed when cURL is unavailable.
 */
class CurlPinnedHttpTransport
{
    /**
     * @param  array{
     *   timeout: int,
     *   max_body_bytes: int,
     *   accept: string,
     *   user_agent: string,
     *   host_header: string,
     *   resolve: string,
     *   classification_prefix_bytes?: int
     * }  $options
     * @return array{
     *   transport_error: string,
     *   status_code: int,
     *   content_type: string,
     *   location: string,
     *   body: string,
     *   truncated_prefix: string,
     *   body_size: int,
     *   body_too_large: bool
     * }
     */
    public function get(string $url, array $validatedIps, array $options): array
    {
        try {
            $this->assertCurlTransportAvailable();

            $connection = $this->pinnedConnection($url, $validatedIps);

            if ($connection === null) {
                throw new RuntimeException('validated_address_missing');
            }

            $maxBodyBytes = max(1, (int) $options['max_body_bytes']);
            $classificationPrefixBytes = max(0, (int) ($options['classification_prefix_bytes'] ?? 0));
            $timeout = max(1, (int) $options['timeout']);
            $client = $this->createCurlClient();
            $curlResolveOption = defined('CURLOPT_RESOLVE') ? constant('CURLOPT_RESOLVE') : 10203;
            $request = new Request('GET', $url, [
                'Host' => $connection['host_header'] !== ''
                    ? $connection['host_header']
                    : (string) $options['host_header'],
                'User-Agent' => (string) $options['user_agent'],
                'Accept' => (string) $options['accept'],
                'Accept-Encoding' => 'identity',
            ]);

            $response = $client->send($request, [
                'allow_redirects' => false,
                'verify' => true,
                'cookies' => false,
                'http_errors' => false,
                'stream' => true,
                'decode_content' => false,
                'connect_timeout' => $timeout,
                'timeout' => $timeout,
                'curl' => [
                    $curlResolveOption => [$connection['resolve']],
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_REDIR_PROTOCOLS => 0,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_MAXREDIRS => 0,
                ],
            ]);

            return $this->readBoundedResponse($response, $maxBodyBytes, $classificationPrefixBytes);
        } catch (RuntimeException $exception) {
            return $this->transportFailure($exception->getMessage());
        } catch (\Throwable $throwable) {
            $message = $throwable->getMessage();

            return $this->transportFailure($message !== '' ? $message : 'request_failed');
        }
    }

    public function assertCurlTransportAvailable(): void
    {
        if (! extension_loaded('curl') || ! function_exists('curl_init')) {
            throw new RuntimeException('curl_extension_unavailable');
        }

        if (! class_exists(CurlHandler::class)) {
            throw new RuntimeException('curl_handler_unavailable');
        }
    }

    public function createCurlClient(): Client
    {
        $this->assertCurlTransportAvailable();

        $handler = new CurlHandler;
        $stack = HandlerStack::create($handler);

        if ($this->stackContainsStreamHandler($stack)) {
            throw new RuntimeException('stream_handler_forbidden');
        }

        return new Client([
            'handler' => $stack,
            'http_errors' => false,
            'allow_redirects' => false,
            'verify' => true,
            'cookies' => false,
        ]);
    }

    public function usesCurlHandler(Client $client): bool
    {
        $config = method_exists($client, 'getConfig') ? $client->getConfig('handler') : null;

        if (! $config instanceof HandlerStack) {
            return false;
        }

        return $this->stackContainsCurlHandler($config)
            && ! $this->stackContainsStreamHandler($config);
    }

    private function stackContainsCurlHandler(HandlerStack $stack): bool
    {
        $handler = $this->resolveStackHandler($stack);

        return $handler instanceof CurlHandler;
    }

    private function stackContainsStreamHandler(HandlerStack $stack): bool
    {
        $handler = $this->resolveStackHandler($stack);

        return $handler instanceof StreamHandler;
    }

    private function resolveStackHandler(HandlerStack $stack): mixed
    {
        $reflection = new \ReflectionClass($stack);

        if (! $reflection->hasProperty('handler')) {
            return null;
        }

        $property = $reflection->getProperty('handler');
        $property->setAccessible(true);

        return $property->getValue($stack);
    }

    /**
     * @param  array<int, string>  $validatedIps
     * @return array{resolve: string, host_header: string}|null
     */
    public function pinnedConnection(string $url, array $validatedIps): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = trim((string) $parts['host'], '[]');
        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);
        $ips = [];

        foreach ($validatedIps as $ip) {
            if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $normalized = strtolower(trim($ip, '[]'));
            $ips[$normalized] = str_contains($normalized, ':')
                ? "[{$normalized}]"
                : $normalized;
        }

        if ($host === '' || $port < 1 || $port > 65535 || $ips === []) {
            return null;
        }

        $defaultPort = ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
        $hostHeader = str_contains($host, ':') ? "[{$host}]" : $host;

        return [
            'resolve' => "{$host}:{$port}:".implode(',', array_values($ips)),
            'host_header' => $hostHeader.($defaultPort ? '' : ':'.$port),
        ];
    }

    /**
     * @return array{
     *   transport_error: string,
     *   status_code: int,
     *   content_type: string,
     *   location: string,
     *   body: string,
     *   truncated_prefix: string,
     *   body_size: int,
     *   body_too_large: bool
     * }
     */
    private function readBoundedResponse(
        ResponseInterface $response,
        int $maxBodyBytes,
        int $classificationPrefixBytes,
    ): array {
        $statusCode = $response->getStatusCode();
        $contentType = $this->normalizeContentType($response->getHeaderLine('Content-Type'));
        $location = $this->sanitizeHeader($response->getHeaderLine('Location'));
        $contentLength = $this->parseContentLength($response->getHeaderLine('Content-Length'));
        $declaredTooLarge = $contentLength > $maxBodyBytes;
        $readLimit = $declaredTooLarge && $classificationPrefixBytes > 0
            ? $classificationPrefixBytes
            : $maxBodyBytes + 1;
        $body = $this->readResponseBody($response->getBody(), $readLimit);
        $bodySize = strlen($body);
        $bodyTooLarge = $declaredTooLarge || $bodySize > $maxBodyBytes;
        $truncatedPrefix = $bodyTooLarge && $classificationPrefixBytes > 0
            ? substr($body, 0, $classificationPrefixBytes)
            : '';

        if ($bodyTooLarge) {
            $body = '';
            $bodySize = $classificationPrefixBytes > 0
                ? $maxBodyBytes + 1
                : max($contentLength, $bodySize);
        }

        return [
            'transport_error' => '',
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'location' => $location,
            'body' => $body,
            'truncated_prefix' => $truncatedPrefix,
            'body_size' => $bodySize,
            'body_too_large' => $bodyTooLarge,
        ];
    }

    private function readResponseBody(StreamInterface $stream, int $limit): string
    {
        $body = '';

        while (! $stream->eof() && strlen($body) < $limit) {
            $remaining = $limit - strlen($body);
            $chunk = $stream->read(min(8192, $remaining));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;
        }

        return $body;
    }

    private function normalizeContentType(string $contentType): string
    {
        $separator = strpos($contentType, ';');

        return trim($separator === false ? $contentType : substr($contentType, 0, $separator));
    }

    private function sanitizeHeader(string $value): string
    {
        $value = trim($value);

        if (strlen($value) > 2048 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            return '';
        }

        return $value;
    }

    private function parseContentLength(string $value): int
    {
        if (preg_match('/^[0-9]+$/', trim($value)) !== 1) {
            return 0;
        }

        $normalized = ltrim(trim($value), '0');

        if ($normalized === '') {
            return 0;
        }

        if (strlen($normalized) > 18) {
            return PHP_INT_MAX;
        }

        return min(PHP_INT_MAX, (int) $normalized);
    }

    /**
     * @return array{
     *   transport_error: string,
     *   status_code: int,
     *   content_type: string,
     *   location: string,
     *   body: string,
     *   truncated_prefix: string,
     *   body_size: int,
     *   body_too_large: bool
     * }
     */
    private function transportFailure(string $message): array
    {
        return [
            'transport_error' => $message !== '' ? $message : 'request_failed',
            'status_code' => 0,
            'content_type' => '',
            'location' => '',
            'body' => '',
            'truncated_prefix' => '',
            'body_size' => 0,
            'body_too_large' => false,
        ];
    }
}
