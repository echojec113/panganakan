<?php

namespace App\Services;

use App\Models\PrenatalVisit;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\ClinicalInteractionEvidence;

/**
 * Builds the immutable referral assessment snapshot from ALREADY-PERSISTED
 * PrenatalVisit assessment output.
 *
 * The snapshot is a controlled, read-only copy of what was persisted at the
 * time the assessment was recorded. It never re-runs clinical logic: it does
 * not invoke RiskAssessmentService, BloodPressureAssessmentService, ML, or the
 * ClinicalInteractionEngine, and it performs no new queries beyond reading the
 * persisted visit (interaction evidence is copied from
 * assessment_metadata.interaction_evidence).
 *
 * Immutability contract: once written into a referral.assessment_snapshot by
 * Phase 16C, the snapshot is never regenerated automatically. Visits,
 * ultrasounds, registry statuses, or clinical rule versions changing later must
 * not alter the stored snapshot.
 *
 * The snapshot carries no patient PII and no arbitrary notes.
 */
class ReferralAssessmentSnapshotService
{
    /** Internal schema version for the referral snapshot shape. */
    public const SNAPSHOT_SCHEMA_VERSION = '1.0.0';

    /**
     * Build a controlled, readable reason-for-referral prefill from a snapshot.
     *
     * Precedence: (1) interaction/factor labels, (2) triggered rule labels,
     * (3) the persisted recommendation, (4) the persisted assessment summary.
     * This is editable clinical copy for the staff member; it NEVER modifies
     * the immutable snapshot. No PII and no raw developer codes are emitted.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function prefillReason(array $snapshot): string
    {
        $parts = [];

        foreach ($snapshot['interaction_evidence'] ?? [] as $interaction) {
            if (! empty($interaction['label'])) {
                $parts[] = (string) $interaction['label'];
            }
        }

        foreach ($snapshot['factor_evidence'] ?? [] as $factor) {
            if (! empty($factor['label'])) {
                $parts[] = (string) $factor['label'];
            }
        }

        foreach ($snapshot['rule_reasons'] ?? [] as $label) {
            if (is_string($label) && $label !== '') {
                $parts[] = $label;
            }
        }

        $parts = array_values(array_unique(array_map('trim', $parts)));
        if ($parts !== []) {
            return implode('; ', $parts);
        }

        foreach (['recommendation', 'assessment'] as $key) {
            $copy = $snapshot[$key] ?? null;
            if (is_string($copy) && trim($copy) !== '') {
                return trim($copy);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null Null when the visit carries no
     *         structured persisted assessment metadata to snapshot
     *         (legacy/manual flow). A legacy HIGH visit without
     *         assessment_metadata is NOT eligible for an assessment-linked
     *         referral; it remains reachable through the manual workflow.
     */
    public function fromPrenatalVisit(PrenatalVisit $visit): ?array
    {
        $metadata = is_array($visit->assessment_metadata) ? $visit->assessment_metadata : [];

        if ($visit->risk_level === null || $metadata === []) {
            return null;
        }

        $context = is_array($metadata['context'] ?? null) ? $metadata['context'] : [];

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'prenatal_visit_id' => (int) $visit->id,
            'visit_date' => $visit->visit_date?->toDateString(),
            'risk_level' => $visit->risk_level,
            'decision_source' => $visit->decision_source,
            'urgency' => $visit->urgency,
            'assessment' => $visit->assessment,
            'recommendation' => $visit->recommendation,
            'rule_reasons' => \App\Support\ListNormalizer::normalize($visit->rule_reasons),
            'factor_evidence' => ClinicalFactorEvidence::normalizeList($visit->factor_evidence),
            'interaction_evidence' => ClinicalInteractionEvidence::normalizeList($metadata['interaction_evidence'] ?? []),
            'bp_assessment' => is_array($visit->bp_assessment) ? $visit->bp_assessment : [],
            'assessment_date' => $context['assessment_date'] ?? $visit->visit_date?->toDateString(),
            'assessed_at' => $metadata['assessed_at'] ?? null,
            'versions' => is_array($metadata['versions'] ?? null) ? $metadata['versions'] : null,
        ];
    }
}