<?php

namespace App\Support;

/**
 * Presentation-only trimester classification.
 *
 * Pure display helper: it never persists or submits anything and never feeds
 * clinical, risk or validation logic. It exists so the server-rendered
 * Patient Profile and the browser-side prenatal indicator share the exact
 * same boundaries as components/trimester-visibility.blade.php:
 *
 *   1.0 <= GA < 13.0  -> 1st Trimester
 *   13.0 <= GA < 28.0 -> 2nd Trimester
 *   GA >= 28.0        -> 3rd Trimester
 *   below 1.0 / blank / invalid -> not classified
 *
 * The input is read as a decimal and is never rounded before classifying.
 */
class TrimesterClassifier
{
    public const FIRST = 1;

    public const SECOND = 2;

    public const THIRD = 3;

    /**
     * Returns 1, 2 or 3, or null when the gestational age cannot be classified.
     */
    public static function classify(int|float|string|null $gestationalAge): ?int
    {
        if ($gestationalAge === null || $gestationalAge === '') {
            return null;
        }

        if (!is_numeric($gestationalAge) || !is_finite((float) $gestationalAge)) {
            return null;
        }

        $weeks = (float) $gestationalAge;

        if ($weeks < 1.0) {
            return null;
        }

        if ($weeks < 13.0) {
            return self::FIRST;
        }

        if ($weeks < 28.0) {
            return self::SECOND;
        }

        return self::THIRD;
    }

    /**
     * Returns the display label, or null when there is no valid trimester.
     *
     * A null result must be rendered as an unavailable state (for example an
     * em dash) rather than a guessed trimester.
     */
    public static function label(int|float|string|null $gestationalAge): ?string
    {
        return match (self::classify($gestationalAge)) {
            self::FIRST => '1st Trimester',
            self::SECOND => '2nd Trimester',
            self::THIRD => '3rd Trimester',
            default => null,
        };
    }
}
