<?php

namespace App\Support;

/**
 * Centralized, immutable source of assessment/clinical rule/context versions.
 *
 * Sprint version numbers must never be used as permanent clinical versions.
 * All consumers read from this single source so version strings are never
 * duplicated across controllers or services.
 */
final class AssessmentVersion
{
    /** Assessment engine internal version (semantic). */
    public const ASSESSMENT_ENGINE_VERSION = '1.0.0';

    /** Clinical rule set internal version (semantic). */
    public const CLINICAL_RULE_VERSION = '1.0.0';

    /** Assessment context schema version (integer). */
    public const CONTEXT_VERSION = 1;

    /**
     * The full version map, encouraged for persistence/metadata use.
     *
     * @return array{assessment_engine: string, clinical_rules: string, context: int}
     */
    public static function versions(): array
    {
        return [
            'assessment_engine' => self::ASSESSMENT_ENGINE_VERSION,
            'clinical_rules' => self::CLINICAL_RULE_VERSION,
            'context' => self::CONTEXT_VERSION,
        ];
    }

    /**
     * Guard against accidental mutation: returns a fresh array each call.
     *
     * @return array{assessment_engine: string, clinical_rules: string, context: int}
     */
    public function toArray(): array
    {
        return self::versions();
    }
}