<?php

namespace StudyMentor\ContentEngine\Http;

defined('ABSPATH') || exit;

final class SafeUrlGuard
{
    private const IPV4_BLOCKED_CIDRS = array(
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
    );

    private const IPV6_BLOCKED_CIDRS = array(
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    );

    /**
     * @param string $url
     * @param array<int, string> $allowedDomains
     * @return array{ok: bool, error: string, url: string}
     */
    public function validate($url, array $allowedDomains)
    {
        if ($allowedDomains === array()) {
            return $this->failure('allowed_domains_empty');
        }

        $normalizedDomains = $this->normalizeAllowedDomains($allowedDomains);

        if ($normalizedDomains === array()) {
            return $this->failure('allowed_domains_invalid');
        }

        $parsed = $this->parseAndNormalizeUrl($url);

        if ($parsed['ok'] !== true) {
            return $this->failure($parsed['error']);
        }

        $host = $parsed['host'];

        if (!$this->hostMatchesAllowedDomains($host, $normalizedDomains)) {
            return $this->failure('host_not_allowed');
        }

        if ($this->isLiteralIpHost($host)) {
            if (!$this->isPublicIpAddress($host)) {
                return $this->failure('non_public_address');
            }

            return array(
                'ok' => true,
                'error' => '',
                'url' => $parsed['url'],
            );
        }

        $resolvedAddresses = $this->resolveHostAddresses($host);

        if ($resolvedAddresses === array()) {
            return $this->failure('dns_resolution_failed');
        }

        foreach ($resolvedAddresses as $address) {
            if (!$this->isPublicIpAddress($address)) {
                return $this->failure('non_public_address');
            }
        }

        return array(
            'ok' => true,
            'error' => '',
            'url' => $parsed['url'],
        );
    }

    /**
     * @param array<int, string> $allowedDomains
     * @return array<int, string>
     */
    private function normalizeAllowedDomains(array $allowedDomains)
    {
        $normalized = array();

        foreach ($allowedDomains as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $value = strtolower(trim($domain));

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{ok: bool, error: string, url: string, host: string}
     */
    private function parseAndNormalizeUrl($url)
    {
        $trimmed = trim((string) $url);

        if ($trimmed === '') {
            return array('ok' => false, 'error' => 'invalid_url', 'url' => '', 'host' => '');
        }

        $parsed = wp_parse_url($trimmed);

        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return array('ok' => false, 'error' => 'invalid_url', 'url' => '', 'host' => '');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return array('ok' => false, 'error' => 'credentials_not_allowed', 'url' => '', 'host' => '');
        }

        $scheme = strtolower((string) $parsed['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return array('ok' => false, 'error' => 'invalid_scheme', 'url' => '', 'host' => '');
        }

        $host = $this->normalizeHostname((string) $parsed['host']);

        if ($host === '') {
            return array('ok' => false, 'error' => 'invalid_host', 'url' => '', 'host' => '');
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;

        if ($port === 80 && $scheme === 'http') {
            $port = null;
        }

        if ($port === 443 && $scheme === 'https') {
            $port = null;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';

        if ($path !== '') {
            $path = preg_replace('#/+#', '/', $path);

            if ($path !== '/' && substr($path, -1) === '/') {
                $path = rtrim($path, '/');
            }
        }

        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $normalizedUrl = $scheme . '://' . $host;

        if ($port !== null) {
            $normalizedUrl .= ':' . $port;
        }

        $normalizedUrl .= $path . $query;

        return array(
            'ok' => true,
            'error' => '',
            'url' => $normalizedUrl,
            'host' => $host,
        );
    }

    private function normalizeHostname($host)
    {
        $host = strtolower(trim((string) $host));
        $host = rtrim($host, '.');

        if ($host === '') {
            return '';
        }

        if ($this->isLiteralIpHost($host)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $packed = @inet_pton($host);

                if ($packed === false) {
                    return '';
                }

                $expanded = inet_ntop($packed);

                return is_string($expanded) ? strtolower($expanded) : '';
            }

            return $host;
        }

        if (preg_match('/[^\x20-\x7E]/', $host) === 1) {
            if (!function_exists('idn_to_ascii')) {
                return '';
            }

            $asciiHost = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($asciiHost === false || !is_string($asciiHost) || $asciiHost === '') {
                return '';
            }

            $host = strtolower($asciiHost);
        }

        if (function_exists('filter_var')) {
            if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                return '';
            }
        } elseif (
            preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host) !== 1
            && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1
        ) {
            return '';
        }

        return $host;
    }

    /**
     * @param array<int, string> $allowedDomains
     */
    private function hostMatchesAllowedDomains($host, array $allowedDomains)
    {
        foreach ($allowedDomains as $allowedDomain) {
            if ($host === $allowedDomain) {
                return true;
            }
        }

        return false;
    }

    private function isLiteralIpHost($host)
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return array<int, string>
     */
    private function resolveHostAddresses($host)
    {
        $addresses = array();
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
                    if (!is_array($record)) {
                        continue;
                    }

                    if (isset($record['type'], $record['ip']) && $record['type'] === 'A') {
                        $addresses[] = (string) $record['ip'];
                    }

                    if (isset($record['type'], $record['ipv6']) && $record['type'] === 'AAAA') {
                        $addresses[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === array() && function_exists('gethostbynamel')) {
            $ipv4List = @gethostbynamel($host);

            if (is_array($ipv4List)) {
                foreach ($ipv4List as $ipv4) {
                    $addresses[] = (string) $ipv4;
                }
            }
        }

        $unique = array();

        foreach ($addresses as $address) {
            $normalized = strtolower(trim($address));

            if ($normalized !== '') {
                $unique[$normalized] = $normalized;
            }
        }

        return array_values($unique);
    }

    private function isPublicIpAddress($address)
    {
        $address = strtolower(trim((string) $address));

        if ($address === '') {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (
                filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
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

            if (
                filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                ) === false
            ) {
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

    private function extractIpv4FromMappedAddress($address)
    {
        $packed = @inet_pton($address);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        if (substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            return inet_ntop(substr($packed, 12, 4));
        }

        if (substr($packed, 0, 12) === "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00") {
            return inet_ntop(substr($packed, 12, 4));
        }

        return null;
    }

    private function ipv4InCidr($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $subnet = $parts[0];
        $bits = (int) $parts[1];
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function ipv6InCidr($ip, $cidr)
    {
        $parts = explode('/', $cidr, 2);

        if (count($parts) !== 2) {
            return false;
        }

        $subnet = $parts[0];
        $bits = (int) $parts[1];
        $ipPacked = @inet_pton($ip);
        $subnetPacked = @inet_pton($subnet);

        if ($ipPacked === false || $subnetPacked === false || $bits < 0 || $bits > 128) {
            return false;
        }

        $fullBytes = (int) floor($bits / 8);
        $partialBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }

        if ($partialBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $partialBits)) & 0xFF;
        $ipByte = ord($ipPacked[$fullBytes]);
        $subnetByte = ord($subnetPacked[$fullBytes]);

        return ($ipByte & $mask) === ($subnetByte & $mask);
    }

    /**
     * @return array{ok: bool, error: string, url: string}
     */
    private function failure($errorCode)
    {
        return array(
            'ok' => false,
            'error' => (string) $errorCode,
            'url' => '',
        );
    }
}
