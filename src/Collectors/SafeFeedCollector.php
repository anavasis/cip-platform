<?php

namespace StudyMentor\ContentEngine\Collectors;

use StudyMentor\ContentEngine\Acquisition\DownloadManager;
use StudyMentor\ContentEngine\Http\SafeFeedFetcher;

defined('ABSPATH') || exit;

final class SafeFeedCollector implements CollectorInterface
{
    private $fetcher;

    public function __construct(SafeFeedFetcher $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @return string
     */
    public function id()
    {
        return 'safe_feed';
    }

    /**
     * Content families this collector can currently serve.
     *
     * @return array<int, string>
     */
    public function supportedFamilies()
    {
        return array(
            DownloadManager::FAMILY_RSS,
            DownloadManager::FAMILY_HTML,
            DownloadManager::FAMILY_JSON,
            DownloadManager::FAMILY_XML,
            DownloadManager::FAMILY_PDF,
        );
    }

    /**
     * @param string $url
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function collect($url, array $allowedDomains)
    {
        return $this->fetcher->fetch($url, $allowedDomains);
    }
}
