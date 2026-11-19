<?php

declare(strict_types=1);

namespace s00d\OnlineSimApi;

/**
 * Client-side normalisation of server quirks, shared by the sections and `parseWebhook`.
 *
 * @internal
 */
final class Normalize
{
    /**
     * A map, or `[]` for anything else (PHP sends an empty map as `[]`, `null` when missing).
     *
     * @return array<array-key, mixed>
     */
    public static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** Float from a number or a numeric string (`"1442.330"`); `null` when missing or not numeric. */
    public static function float(mixed $value): ?float
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }

        return null;
    }

    /** Integer from an integer or an integer string (`"430"`); `null` otherwise. */
    public static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /** String from a string or a number (`2991` → `"2991"`); `null` otherwise. */
    public static function string(mixed $value): ?string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }
}
