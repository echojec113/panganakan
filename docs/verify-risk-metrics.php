<?php

// Read-only. Run inside Tinker: require base_path('docs/verify-risk-metrics.php');
// Independent oracle: sort all non-deleted visits rather than use latestAssessmentIds().
$visits = \App\Models\PrenatalVisit::orderByDesc('visit_date')->orderByDesc('id')->get();
$patients = \App\Models\Patient::get(['id', 'status']);
$ongoingIds = $patients->where('status', 'ONGOING')->modelKeys();
$latest = $visits->unique('patient_id');
$activeVisits = $visits->whereIn('patient_id', $ongoingIds);
$activeLatest = $latest->whereIn('patient_id', $ongoingIds);
$groupRisk = fn ($rows) => $rows->groupBy(fn ($visit) => $visit->risk_level ?? '<NULL>')->map->count()->all();
$overdue = $activeLatest->filter(fn ($visit) => $visit->next_visit_date && $visit->next_visit_date->lt(today()))->count();
$canonicalIds = \App\Models\PrenatalVisit::whereIn('id', \App\Models\PrenatalVisit::latestAssessmentIds())->pluck('id')->sort()->values()->all();
$relationshipIds = \App\Models\Patient::with('latestPrenatalAssessment')->get()->pluck('latestPrenatalAssessment')->filter()->pluck('id')->sort()->values()->all();
dump([
    'all_non_deleted_visits' => $visits->count(),
    'HIGH_visits' => $visits->where('risk_level', 'HIGH')->count(),
    'LOW_visits' => $visits->where('risk_level', 'LOW')->count(),
    'INCOMPLETE_visits' => $visits->where('risk_level', 'ASSESSMENT INCOMPLETE')->count(),
    'grouped_risk_sql' => \App\Models\PrenatalVisit::selectRaw('risk_level, COUNT(*) AS total')->groupBy('risk_level')->get()->toArray(),
    'latest_risk_all_statuses' => $groupRisk($latest),
    'latest_risk_ongoing' => $groupRisk($activeLatest),
    'priority_alerts_and_patient_records_high' => $activeLatest->where('risk_level', 'HIGH')->count(),
    'overdue_follow_ups' => $overdue,
    'admin_displayed_overdue_rows' => min(5, $overdue),
    'patient_records_total' => count($ongoingIds),
    'ongoing_ever_high_before_fix' => $activeVisits->where('risk_level', 'HIGH')->unique('patient_id')->count(),
    'prenatal_page_total' => $activeVisits->count(),
    'prenatal_page_risk' => $groupRisk($activeVisits),
    'prenatal_page_this_month' => $activeVisits->filter(fn ($visit) => $visit->visit_date->format('Y-m') === now()->format('Y-m'))->count(),
    'latest_ids_match_independent_sort' => $canonicalIds === $latest->pluck('id')->sort()->values()->all(),
    'relationship_ids_match_independent_sort' => $relationshipIds === $latest->whereIn('patient_id', $patients->modelKeys())->pluck('id')->sort()->values()->all(),
    'visits_without_live_parent' => \App\Models\PrenatalVisit::whereDoesntHave('patient')->count(),
]);
