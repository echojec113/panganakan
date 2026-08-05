<?php

namespace App\Services;

use App\Models\PrenatalVisit;
use App\ValueObjects\AssessmentResult;

/**
 * Builds the normalized JSON document persisted on prenatal_visits.assessment_metadata.
 *
 * The document only ever carries approved, scalar/nested-array structures. It
 * is additive and versioned: the schema can grow, and every document records
 * the versions used to produce it.
 */
class AssessmentMetadataSerializer
{
    /**
     * @param PrenatalVisit|null $visit When provided (post-persistence), the
     *        context's prenatal_visit_id/date are patched so the snapshot
     *        refers to the exact stored visit. Pre-persistence store() flows
     *        leave them null.
     * @return array<string, mixed>
     */
    public function fromResult(AssessmentResult $result, ?PrenatalVisit $visit = null): array
    {
        $context = $result->context;

        if ($visit !== null && is_array($context)) {
            $context['prenatal_visit_id'] = (int) $visit->id;
            $context['prenatal_visit_date'] = $visit->visit_date?->toDateString() ?? $context['prenatal_visit_date'] ?? null;
        }

        return [
            'context' => $context,
            'interaction_evidence' => $result->interaction_evidence,
            'data_quality_flags' => $result->data_quality_flags,
            'decision_trace' => $result->decision_trace,
            'versions' => $result->versions,
            'assessed_at' => $result->assessed_at,
        ];
    }
}