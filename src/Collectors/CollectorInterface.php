<?php

namespace StudyMentor\ContentEngine\Collectors;

defined('ABSPATH') || exit;

interface CollectorInterface
{
    /**
     * @return string
     */
    public function id();

    /**
     * @param string $url
     * @param array<int, string> $allowedDomains
     * @return array<string, mixed>
     */
    public function collect($url, array $allowedDomains);
}
