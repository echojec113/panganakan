<?php

namespace App\Support;

class ListNormalizer
{
    /**
     * Normalize a legacy list-shaped attribute value into a clean array of
     * non-empty strings so views can safely iterate over it.
     *
     * Handles:
     *  - array                          -> values preserved
     *  - valid JSON string (array)      -> decoded
     *  - valid JSON string (scalar)     -> unwrapped into a single item
     *  - non-empty plain string         -> wrapped as a single item
     *  - null / invalid / empty data    -> empty array
     *
     * Nested arrays, objects and empty entries are filtered out.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    public static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $decoded = json_decode($value, true);
            $isValidJson = json_last_error() === JSON_ERROR_NONE;

            if (is_array($decoded)) {
                $items = $decoded;
            } elseif ($isValidJson && is_string($decoded)) {
                $items = [trim($decoded)];
            } elseif (!$isValidJson && trim($value) !== '') {
                $items = [$value];
            } else {
                $items = [];
            }
        } else {
            $items = [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }
            if (is_array($item) || is_object($item)) {
                continue;
            }
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $normalized[] = $text;
        }

        return array_values($normalized);
    }
}
