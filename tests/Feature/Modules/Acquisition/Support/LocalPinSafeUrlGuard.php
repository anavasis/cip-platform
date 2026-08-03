<?php

namespace Tests\Feature\Modules\Acquisition\Support;

use App\Modules\Acquisition\Infrastructure\Http\SafeUrlGuard;
use Closure;

/**
 * Test-only SafeUrlGuard that may resolve selected hostnames to 127.0.0.1 so a
 * local socket server can exercise real cURL CURLOPT_RESOLVE pinning without
 * public internet access. Literal loopback/link-local URL hosts stay rejected.
 */
final class LocalPinSafeUrlGuard extends SafeUrlGuard
{
    /** @var array<string, true> */
    private array $pinHosts = [];

    private bool $allowLoopbackResolution = false;

    /**
     * @param  array<int, string>  $pinHosts
     * @param  null|Closure(string): array<int, string>  $resolver
     */
    public function __construct(array $pinHosts, ?Closure $resolver = null)
    {
        foreach ($pinHosts as $host) {
            $normalized = strtolower(trim($host));

            if ($normalized !== '') {
                $this->pinHosts[$normalized] = true;
            }
        }

        parent::__construct($resolver ?? function (string $host): array {
            return isset($this->pinHosts[strtolower($host)]) ? ['127.0.0.1'] : [];
        });
    }

    public function validate(string $url, array $allowedDomains): array
    {
        $parts = parse_url(trim($url));
        $host = isset($parts['host']) ? strtolower(trim((string) $parts['host'], '[]')) : '';
        $isLiteralIp = $host !== '' && filter_var($host, FILTER_VALIDATE_IP) !== false;
        $this->allowLoopbackResolution = ! $isLiteralIp && isset($this->pinHosts[$host]);

        try {
            return parent::validate($url, $allowedDomains);
        } finally {
            $this->allowLoopbackResolution = false;
        }
    }

    protected function isPublicIpAddress(string $address): bool
    {
        if ($this->allowLoopbackResolution && $address === '127.0.0.1') {
            return true;
        }

        return parent::isPublicIpAddress($address);
    }
}
