<?php

namespace App\Modules\Acquisition\Infrastructure\Http;

interface FeedFetcherInterface
{
    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, mixed>
     */
    public function fetch(string $url, array $allowedDomains): array;

    /**
     * @param  array<int, string>  $allowedDomains
     * @return array<string, mixed>
     */
    public function fetchForConnectivityAudit(string $url, array $allowedDomains): array;
}
