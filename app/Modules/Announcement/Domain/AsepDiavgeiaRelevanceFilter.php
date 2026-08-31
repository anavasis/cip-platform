<?php

namespace App\Modules\Announcement\Domain;

/**
 * Title-only relevance gate for Diavgeia ASEP RSS items (fail-closed).
 */
final class AsepDiavgeiaRelevanceFilter
{
    public function isRelevantTitle(string $title): bool
    {
        $normalized = $this->normalizeTitle($title);

        if ($normalized === '') {
            return false;
        }

        if ($this->matchesRejectPattern($normalized)) {
            return false;
        }

        return $this->matchesAcceptPattern($normalized);
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $title = mb_strtolower($title, 'UTF-8');
        } else {
            $title = strtolower($title);
        }

        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $title);

        return is_string($collapsed) ? trim($collapsed) : trim($title);
    }

    private function matchesRejectPattern(string $normalizedTitle): bool
    {
        $patterns = [
            '/[έε]νταλμ[άα]?\s+πληρωμ/u',
            '/απευθε[ίι]ας\s+αν[άα]θεσ/u',
            '/ορισμ[όo]ς\s+μελ[ώω]ν\s+επιτροπ/u',
            '/αποστολ[ήη]\s+απόφασης\s+επ[ίι]\s+αναπληρ[ώω]σ/u',
            '/τροποπο[ίι]ηση\s+οργανογρ[άα]μματος/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedTitle) === 1) {
                return true;
            }
        }

        return false;
    }

    private function matchesAcceptPattern(string $normalizedTitle): bool
    {
        $patterns = [
            '/προκ[ήη]ρυξη/u',
            '/υποβολ[ήη]\s+αιτ[ήη]σεων/u',
            '/έναρξη\s+υποβολ[ήη]ς\s+αιτ[ήη]σεων/u',
            '/πρ[όo]σκληση\s+υποβολ[ήη]ς\s+δικαιολογητικ/u',
            '/προσωριν[άα]\s+αποτελ[έε]σματα/u',
            '/προσωριν\w+\s+π[ίι]νακ/u',
            '/υποβολ[ήη]\s+ενστ[άα]σεων/u',
            '/οριστικ[άα]\s+αποτελ[έε]σματα/u',
            '/οριστικ\w+\s+π[ίι]νακ/u',
            '/π[ίι]νακ(?:ες|ας|ων)?\s+διοριστ/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedTitle) === 1) {
                return true;
            }
        }

        return false;
    }
}
