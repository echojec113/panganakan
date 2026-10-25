<?php

namespace App\Services;

use App\Models\User;

/** Edit-only display fallback; never mutates the saved User. */
class StaffNamePrefill
{
    /**
     * Single-token Filipino/Spanish compound-surname particles. A three-token
     * legacy name whose middle token is one of these is treated as ambiguous:
     * the token most likely belongs to a compound surname (e.g. "Dela Cruz"),
     * so the name is not split rather than guess a surname or a middle name.
     */
    private const COMPOUND_PARTICLES = [
        'de', 'dela', 'del', 'di', 'da', 'do', 'dos',
        'la', 'le', 'los', 'las', 'san', 'santa', 'santo',
        'van', 'von', 'ten', 'ter', 'den', 'op',
        'bin', 'ibn', 'al', 'el', 'abu', 'umm',
    ];

    public static function fromLegacy(User $staff): ?array
    {
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (trim((string) $staff->{$field}) !== '') {
                return null;
            }
        }

        $parts = preg_split('/\s+/u', trim((string) $staff->name));
        $parts = array_values(array_filter($parts ?: [], static fn ($part) => $part !== ''));
        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || mb_strlen($part) > 100
                || !preg_match("/\A\p{L}[\p{L}\p{M}]*(?:['’\-][\p{L}\p{M}]+)*\z/u", $part)
                || in_array(mb_strtolower($part), ['mr', 'mrs', 'ms', 'dr', 'sir', 'jr', 'sr', 'ii', 'iii', 'iv'], true)) {
                return null;
            }
        }

        if (count($parts) === 2) {
            return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => $parts[1]];
        }

        if (in_array(mb_strtolower($parts[1]), self::COMPOUND_PARTICLES, true)) {
            return null;
        }

        return ['first_name' => $parts[0], 'middle_name' => $parts[1], 'last_name' => $parts[2]];
    }
}
