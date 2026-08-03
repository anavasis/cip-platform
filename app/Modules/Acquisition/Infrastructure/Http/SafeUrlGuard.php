<?php

namespace App\Modules\Acquisition\Infrastructure\Http;

use Closure;

class SafeUrlGuard
{
    private const IPV4_BLOCKED_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const IPV6_BLOCKED_CIDRS = [
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /** @param null|Closure(string): array<int, string> $resolver */
    public function __construct(
        private readonly ?Closure $resolver = null,
    ) {}

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array{ok: bool, error: string, url: string, ips: array<int, string>}
     */
    public function validate(string $url, array $allowedDomains): array
    {
        if ($allowedDomains === []) {
            return $this->failure('allowed_domains_empty');
        }

        $normalizedDomains = $this->normalizeAllowedDomains($allowedDomains);

        if ($normalizedDomains === []) {
            return $this->failure('allowed_domains_invalid');
        }

        $parsed = $this->parseAndNormalizeUrl($url);

        if ($parsed['ok'] !== true) {
            return $this->failure($parsed['error']);
        }

        $host = $parsed['host'];

        if (! in_array($host, $normalizedDomains, true)) {
            return $this->failure('host_not_allowed');
        }

        if ($this->isLiteralIpHost($host)) {
            if (! $this->isPublicIpAddress($host)) {
                return $this->failure('non_public_address');
            }

            return [
                'ok' => true,
                'error' => '',
                'url' => $parsed['url'],
                'ips' => [$host],
            ];
        }

        $resolvedAddresses = $this->resolveHostAddresses($host);

        if ($resolvedAddresses === []) {
            return $this->failure('dns_resolution_failed');
        }

        foreach ($resolvedAddresses as $address) {
            if (! $this->isPublicIpAddress($address)) {
                return $this->failure('non_public_address');
            }
        }

        return [
            'ok' => true,
            'error' => '',
            'url' => $parsed['url'],
            'ips' => $resolvedAddresses,
        ];
    }

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<int, string>
     */
    private function normalizeAllowedDomains(array $allowedDomains): array
    {
        $normalized = [];

        foreach ($allowedDomains as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $value = $this->normalizeHostname($domain);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** @return array{ok: bool, error: string, url: string, host: string} */
    private function parseAndNormalizeUrl(string $url): array
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'invalid_url', 'url' => '', 'host' => ''];
        }

        $parsed = parse_url($trimmed);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return ['ok' => false, 'error' => 'invalid_url', 'url' => '', 'host' => ''];
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return ['ok' => false, 'error' => 'credentials_not_allowed', 'url' => '', 'host' => ''];
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['ok' => false, 'error' => 'invalid_scheme', 'url' => '', 'host' => ''];
        }

        $host = $this->normalizeHostname((string) $parsed['host']);

        if ($host === '') {
            return ['ok' => false, 'error' => 'invalid_host', 'url' => '', 'host' => ''];
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return ['ok' => false, 'error' => 'invalid_url', 'url' => '', 'host' => ''];
        }

        if (($port === 80 && $scheme === 'http') || ($port === 443 && $scheme === 'https')) {
            $port = null;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';

        if ($path !== '') {
            $path = preg_replace('#/+#', '/', $path) ?? '';

            if ($path !== '/' && str_ends_with($path, '/')) {
                $path = rtrim($path, '/');
            }
        }

        $urlHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            ? '['.$host.']'
            : $host;
        $normalizedUrl = $scheme.'://'.$urlHost;

        if ($port !== null) {
            $normalizedUrl .= ':'.$port;
        }

        $normalizedUrl .= $path.(isset($parsed['query']) ? '?'.$parsed['query'] : '');

        return [
            'ok' => true,
            'error' => '',
            'url' => $normalizedUrl,
            'host' => $host,
        ];
    }

    private function normalizeHostname(string $host): string
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if ($host === '') {
            return '';
        }

        if ($this->isLiteralIpHost($host)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $packed = @inet_pton($host);
                $expanded = $packed === false ? false : inet_ntop($packed);

                return is_string($expanded) ? strtolower($expanded) : '';
            }

            return $host;
        }

        if (preg_match('/[^\x20-\x7E]/', $host) === 1) {
            if (! function_exists('idn_to_ascii')) {
                return '';
            }

            $asciiHost = defined('INTL_IDNA_VARIANT_UTS46')
                ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : idn_to_ascii($host);

            if (! is_string($asciiHost) || $asciiHost === '') {
                return '';
            }

            $host = strtolower($asciiHost);
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false ? $host : '';
    }

    private function isLiteralIpHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    /** @return array<int, string> */
    private function resolveHostAddresses(string $host): array
    {
        if ($this->resolver !== null) {
            $resolved = ($this->resolver)($host);

            return is_array($resolved) ? $this->normalizeAddresses($resolved) : [];
        }

        $addresses = [];
        $recordTypes = 0;

        if (defined('DNS_A')) {
            $recordTypes |= DNS_A;
        }

        if (defined('DNS_AAAA')) {
            $recordTypes |= DNS_AAAA;
        }

        if ($recordTypes > 0 && function_exists('dns_get_record')) {
            $records = @dns_get_record($host, $recordTypes);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (! is_array($record)) {
                        continue;
                    }

                    if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                        $addresses[] = (string) $record['ip'];
                    }

                    if (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                        $addresses[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === [] && function_exists('gethostbynamel')) {
            $ipv4List = @gethostbynamel($host);

            if (is_array($ipv4List)) {
                foreach ($ipv4List as $ipv4) {
                    $addresses[] = (string) $ipv4;
                }
            }
        }

        return $this->normalizeAddresses($addresses);
    }

    /**
     * @param  array<int, mixed>  $addresses
     * @return array<int, string>
     */
    private function normalizeAddresses(array $addresses): array
    {
        $unique = [];

        foreach ($addresses as $address) {
            if (! is_string($address)) {
                continue;
            }

            $normalized = strtolower(trim($address));

            if ($normalized !== '') {
                $unique[$normalized] = $normalized;
            }
        }

        return array_values($unique);
    }

    protected function isPublicIpAddress(string $address): bool
    {
        $address = strtolower(trim($address));

        if ($address === '') {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                return false;
            }

            foreach (self::IPV4_BLOCKED_CIDRS as $cidr) {
                if ($this->ipv4InCidr($address, $cidr)) {
                    return false;
                }
            }

            return true;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $mappedIpv4 = $this->extractIpv4FromMappedAddress($address);

            if ($mappedIpv4 !== null) {
                return $this->isPublicIpAddress($mappedIpv4);
            }

            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                return false;
            }

            foreach (self::IPV6_BLOCKED_CIDRS as $cidr) {
                if ($this->ipv6InCidr($address, $cidr)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function extractIpv4FromMappedAddress(string $address): ?string
    {
        $packed = @inet_pton($address);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff"
            || substr($packed, 0, 12) === "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00") {
            $ipv4 = inet_ntop(substr($packed, 12, 4));

            return is_string($ipv4) ? $ipv4 : null;
        }

        return null;
    }

    private function ipv4InCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $bits = (int) $parts[1];
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($parts[0]);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function ipv6InCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $bits = (int) $parts[1];
        $ipPacked = @inet_pton($ip);
        $subnetPacked = @inet_pton($parts[0]);

        if ($ipPacked === false || $subnetPacked === false || $bits < 0 || $bits > 128) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $partialBits = $bits % 8;

        if ($fullBytes > 0
            && substr($ipPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }

        if ($partialBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $partialBits)) & 0xFF;

        return (ord($ipPacked[$fullBytes]) & $mask) === (ord($subnetPacked[$fullBytes]) & $mask);
    }

    /** @return array{ok: false, error: string, url: string, ips: array<int, string>} */
    private function failure(string $errorCode): array
    {
        return ['ok' => false, 'error' => $errorCode, 'url' => '', 'ips' => []];
    }
}
