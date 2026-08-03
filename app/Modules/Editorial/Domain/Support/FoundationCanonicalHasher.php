<?php

namespace App\Modules\Editorial\Domain\Support;

/**
 * Foundation-compatible canonical JSON hashing (json_encode path).
 *
 * Matches editorial-foundation builders/validators when the WordPress JSON helper is absent.
 */
final class FoundationCanonicalHasher
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function hash(array $payload): string
    {
        $canonical = self::canonicalize($payload);
        $encoded = json_encode($canonical);

        if (! is_string($encoded) || $encoded === '') {
            $encoded = serialize($canonical);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = self::canonicalize($item);
        }

        return $out;
    }
}
