<?php

namespace App\Modules\Acquisition\Infrastructure\Collectors;

use App\Modules\Acquisition\Application\DownloadManager;
use App\Modules\Acquisition\Domain\Collectors\CollectorInterface;
use App\Modules\Acquisition\Infrastructure\Http\FeedFetcherInterface;

final readonly class SafeFeedCollector implements CollectorInterface
{
    public function __construct(private FeedFetcherInterface $fetcher) {}

    public function id(): string
    {
        return 'safe_feed';
    }

    /** @return array<int, string> */
    public function supportedFamilies(): array
    {
        return [
            DownloadManager::FAMILY_RSS,
            DownloadManager::FAMILY_HTML,
            DownloadManager::FAMILY_JSON,
            DownloadManager::FAMILY_XML,
            DownloadManager::FAMILY_PDF,
        ];
    }

    public function collect(string $url, array $allowedDomains): array
    {
        return $this->fetcher->fetch($url, $allowedDomains);
    }
}
