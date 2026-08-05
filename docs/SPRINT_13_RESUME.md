# Sprint 13 Resume / Handoff

Branch: `refactor/sprint-13-assessment-context`

## Status: Sprint 13 Checkpoint B + Correctness Patch COMPLETE — NO NEW ACTIVE RULES

The context-aware assessment architecture is implemented, tested, and documented.
The system now snapshots the exact clinical context that produced an assessment
(which records were present, which ultrasound was selected and its three findings,
which versions ran), records an approved human-readable 7-step decision trace,
includes active data-quality verification flags, and carries empty interaction
evidence — all stored as `prenatal_visits.assessment_metadata` JSON.

A correctness patch resolved the BP-URG trace contradiction, made the ultrasound
context fully reproducible via `ultrasound_inputs`, cleaned up the `AssessmentResult`
versions contract, clarified the date/time contract, and made the source preview
accurate. No migrations were run; no clinical rule, BP threshold, diagnosis,
safety hierarchy, ML/Python, referral, or sync behavior changed.

Sprint 13 deliberately introduces NO new clinical rules, scores, factors, or
thresholds. `ClinicalInteractionRegistry::activeCodes()` returns an empty set and
`DataQualityFlagRegistry` flags never enter `factor_evidence`, never classify HIGH,
and never drive urgency or count escalation.

## What was built

### Assessment context (reproducibility snapshot)

- `app/Support/AssessmentVersion.php` — `ASSESSMENT_ENGINE_VERSION`, `CLINICAL_RULE_VERSION`, `CONTEXT_VERSION`; `versions()` array.
- `app/ValueObjects/AssessmentContext.php` — immutable value object snapshotting assessment date, patient id/status, GA, LMP/EDD, selected ultrasound (id/date + the three `ultrasound_inputs` findings), active medical history + birth plan records, visit, and presence flags; `normalizeList()` sanitizes stored arrays.
- `app/Services/AssessmentContextBuilder.php` — builds the context in one pass from `patient`, optional `visit`, `inputs`, and `assessmentDate`; selects the ultrasound deterministically (`scan_date DESC, created_at DESC, id DESC`); accepts optional pre-computed duplicate counts to avoid repeat queries.

### Interaction evidence (zero active in Sprint 13)

- `app/Support/ClinicalInteractionRegistry.php` — registry of candidate interaction codes (`CLIN-INTER-*`), all DRAFT/DEFERRED; `activeCodes()` returns `[]` in Sprint 13.
- `app/ValueObjects/ClinicalInteractionEvidence.php` — immutable VO; `normalizeList()` strips unknown keys.
- `app/Services/ClinicalInteractionEngine.php` — evaluates only `activeCodes()` (none), no DB/ML/writes/scoring, always returns `[]`.

### Data-quality verification flags (never clinical factors)

- `app/Support/DataQualityFlagRegistry.php` — ACTIVE flags: `DQ-SOURCE-FUTURE-DATED`, `DQ-ULTRASOUND-MISSING-FIELDS`, `DQ-DUP-MEDICAL-HISTORY`, `DQ-DUP-BIRTH-PLAN`; DEFERRED: `DQ-LMP-MISSING`, `DQ-EDD-MISSING`, `DQ-GA-DATE-MISMATCH`, `DQ-ULTRASOUND-STALE`. Severities INFO / VERIFY / IMPORTANT.
- `app/ValueObjects/DataQualityFlag.php` — immutable VO with approved keys and severity validation.
- `app/Services/AssessmentDataQualityService.php` — evaluates the four active flags; accepts optional `$duplicateCounts`.

### Decision trace

- `app/ValueObjects/DecisionTraceStep.php` — immutable step with approved keys `step_code`, `status`, `summary`, `related_factor_codes`, `related_interaction_codes`, `missing_records`, `assessed_at`; allowed statuses `COMPLETED`/`TRIGGERED`/`SKIPPED`/`BLOCKED`; `normalizeList()` null-safe. Never carries stack traces, raw Python output, or technical exceptions.
- `app/Services/DecisionTraceBuilder.php` — **approved 7-step pipeline in exact order** (`CONTEXT_BUILT → URGENT_BP_CHECK → COMPLETENESS_CHECK → STANDALONE_RULE_EVALUATION → INTERACTION_RULE_EVALUATION → ML_EVALUATION → FINAL_DECISION`), built purely from the final result (no re-evaluation). BP-URG overrides completeness: `COMPLETENESS_CHECK` is `COMPLETED`; missing records are **preserved but did not block** the urgent result.

### Ultrasound inputs (reproducible snapshot)

- `app/ValueObjects/UltrasoundSnapshot.php` — controlled, immutable input to the clinical rule engine: `ultrasound_id`, `ultrasound_date`, and exactly three findings `presentation`, `amniotic_fluid`, `fetal_heartbeat`. No model internals or PII.
- `app/ValueObjects/AssessmentContext.php` — `ultrasound_inputs` allowed key restricted to the three findings by `sanitizeUltrasoundInputs()`.
- `app/Services/AssessmentContextBuilder.php` — `buildForPatient()` accepts the already-selected `Ultrasound` record and persists `ultrasound_inputs` in the context; selection happens once, no downstream re-selection of "latest".
- `app/Services/AssessmentDataQualityService.php` — the DQ missing-fields check reads `$context->ultrasound_inputs` only (no `Ultrasound::find()`).
- `app/Services/ClinicalRuleEngine.php` — `evaluate()`/`evaluateDetailed()` accept `?UltrasoundSnapshot`.

### Date/time contract

- `context.assessment_date` = the clinical/date anchor for source-date checks (a future-dated ultrasound is flagged against it); it is not the execution time.
- `assessed_at` = exact engine execution timestamp; `RiskAssessmentService::finalize()` computes it and passes it to `DecisionTraceBuilder::build()` so every trace step carries the same value (builder falls back to `$result->assessed_at`).

### Metadata document

- `app/Services/AssessmentMetadataSerializer.php` — builds the scoped `assessment_metadata` document (context, interaction_evidence, data_quality_flags, decision_trace, versions, assessed_at); optionally patches `prenatal_visit_id` / `prenatal_visit_date` after persistence.
- `app/ValueObjects/AssessmentResult.php` — 19 approved keys (adds `context`, `interaction_evidence`, `data_quality_flags`, `decision_trace`, `versions`, `assessed_at`); `versions` are always derived from `AssessmentVersion::versions()` (no injected `$versions`, so a fresh result can never forge historical versions).
- `database/migrations/2026_08_05_000002_add_assessment_metadata_to_prenatal_visits_table.php` — nullable JSON column after `factor_evidence`. **NOT EXECUTED.**
- `app/Models/PrenatalVisit.php` — `assessment_metadata` fillable + array cast.

### Integration

- `app/Services/RiskAssessmentService.php` — builds context once via `AssessmentContextBuilder`, reuses the context-selected ultrasound, evaluates DQ flags once, attaches all metadata in `finalize()`.
- `app/Services/PatientAssessmentRecalculationService.php` — injects `AssessmentMetadataSerializer`; persists metadata on recalculation.
- `app/Http/Controllers/PrenatalVisitController.php` — store/update pass the visit date as assessment date and persist `assessment_metadata`; create/edit build a "Source Preview" panel.

### UI / print

- `resources/views/patients/show.blade.php` — three new cards: "Assessment Context Used", "Data Requiring Verification", "Assessment Decision Path". The decision path renders the 7 trace steps with status pills + summary + related factor/interaction codes + missing records; the context card shows `ultrasound_inputs`.
- `resources/views/risk/monitoring.blade.php` — "Verify N" badges on the mobile card and desktop table when a latest visit has verification flags.
- `resources/views/prenatal_visits/create.blade.php` + `edit.blade.php` — collapsible "Source Preview" panels. On create, the panel is server-generated only for the preselected patient and is labeled "Source preview (preselected patient)"; JS hides it when the patient changes (UI-only, no client-side risk calculation).
- `resources/views/exports/patient-record.blade.php` — printable tables for context (incl. `ultrasound_inputs`), verification flags, and the 7-step decision trace.

## Tests

- New: `UltrasoundSnapshotTest`; rewritten: `DecisionTraceStepTest` (approved keys + null-safe normalize + 7-step order), `DecisionTraceBuilderTest` (pipeline order + scenarios A–G: BP-URG+complete, BP-URG+missing, BP-H+missing, rule HIGH, ML HIGH, ML LOW, invalid ML → INCOMPLETE; BP-URG no-stop claim; assessed_at passthrough; no technical output), `AssessmentContextBuilderTest` (ultrasound id/date/values captured, normal values preserved, later model edits do not mutate the context, pre-selected ultrasound accepted), `AssessmentDataQualityServiceTest` (DQ reads context values, future-dated uses `assessment_date` anchor, `assessment_date` distinct from `now`), `AssessmentContextTest` (ultrasound_inputs sanitization), `AssessmentResultTest` (versions reflect `AssessmentVersion::versions()`, no `versions` ctor param), `AssessmentMetadataSerializerTest` (new trace keys), `ClinicalRuleEngineTest` (passes `UltrasoundSnapshot`), `Feature/AssessmentMetadataPersistenceTest` (trace step_codes + ultrasound_inputs + assessment_date vs assessed_at).
- Regression suites green: Sprint 10 BP, Sprint 11 sync/recalc, Sprint 12 evidence.
- Full suite: **374 passed, 3 failed** (1412 assertions). The 3 failures are pre-existing and unrelated (ExampleTest guest redirect 302, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403). **Zero new regressions.**

## Constraints honored

- No clinical thresholds, decision hierarchy, BP behavior, ML/Python, referrals, or sync logic changed.
- No new active clinical rules, scores, or outcomes. Interaction registry active set is empty.
- Migration `2026_08_05_000002_...` created but NOT executed (still Pending in `migrate:status`).
- Legacy records keep `NULL` `assessment_metadata`; no backfill.

## Next steps (when resuming)

1. Manually inspect and execute pending migrations (BP verification, notes/recommendation, factor_evidence, assessment_metadata) after review.
2. Future sprint: promote a candidate interaction (`CLIN-INTER-*`) from DRAFT to ACTIVE only with clinical approval — the engine and registry are ready.
3. Future sprint: activate deferred DQ flags (e.g., `DQ-ULTRASOUND-STALE`, `DQ-GA-DATE-MISMATCH`) only with clinical approval.
4. Future sprint: MAT-WARN evaluation + referral integration remains deferred pending clinical sign-off.
