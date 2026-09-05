# Implementation Progress

## Sprint 1 — RiskAssessmentService

Status: Complete

Completed:

- Extracted risk assessment from PrenatalVisitController
- Added constructor dependency injection
- Removed fake Request objects during recalculation
- Replaced direct PrenatalVisitController construction
- Normalized ML inputs
- Verified HIGH, LOW, and ASSESSMENT INCOMPLETE in the browser

## Sprint 2 — CompletenessValidator

Status: Complete

Completed:

- Created app/Services/CompletenessValidator.php
- Extracted required-record checks
- Added six passing tests

## Sprint 3 — ClinicalRuleEngine

Status: Complete

Completed:

- Created app/Services/ClinicalRuleEngine.php
- Extracted all deterministic HIGH-risk rules
- Preserved reason wording and evaluation order
- Added thirteen passing tests
- Existing completeness tests still pass

## Sprint 4 — MachineLearningService

Status: Complete

Completed:

- Created `app/Services/MachineLearningService.php` — extracted all Python Random Forest integration
- `buildFeatureArray()` normalizes 12 features to numeric values, preserves required order
- `makeResult()` validates parsed output as valid LOW/HIGH or invalid
- `predict()` orchestrates feature array, Python path resolution, shell execution, and result parsing
- Modified `app/Services/RiskAssessmentService.php` — injected `MachineLearningService`, replaced 60-line inline ML block with `$this->machineLearningService->predict($patient, $inputs)`
- All diagnostic logging preserved: ML FEATURE ARRAY, PYTHON_PATH warning, ML COMMAND, ML RAW OUTPUT, PARSED OUTPUT
- Created `tests/Unit/Services/MachineLearningServiceTest.php` — 11 tests, 30 assertions

Design defense: MachineLearningService isolates the external Python model so deterministic clinical rules and final decision logic remain independent from model execution failures. If predict.py fails, the structured result returns `valid: false`, the engine never accidentally defaults to LOW, and RiskAssessmentService returns ASSESSMENT INCOMPLETE via the existing fallback path.

UI documentation: No Blade or frontend files changed in Sprint 4. Future explainability UI may display staff-friendly ML status, but raw command output and Python errors must not be shown directly to clinic users.

## Known Issues

- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0
- ASSESSMENT INCOMPLETE may still appear as Low in an obsolete Blade branch
- Referral feature test has a pre-existing 403 authorization failure
- Other known pre-existing test failures remain unrelated to CDSS extraction
- `previous_cs` and `miscarriage` columns are present in the Patient model's fillable array but absent from all database migrations — they exist in the production database but cause test failures on fresh in-memory SQLite

## Sprint 5 — DecisionIntegrationService

Status: Complete

Completed:

- Created `app/Services/DecisionIntegrationService.php` — centralizes the five-path decision hierarchy
- `decide(missingRecords, ruleReasons, mlResult)` implements the approved safety hierarchy:
  1. Missing records → ASSESSMENT INCOMPLETE (COMPLETENESS)
  2. Rule reasons → HIGH (RULE_BASED)
  3. Valid ML HIGH → HIGH (MACHINE_LEARNING)
  4. Valid ML LOW → LOW (MACHINE_LEARNING)
  5. Invalid ML → ASSESSMENT INCOMPLETE (MACHINE_LEARNING_INVALID)
- Preserves exact existing wording for all five response paths, including "and X more factor(s)." summary
- Adds structured metadata keys to every result: `decision_source`, `missing_records`, `rule_reasons`, `ml_prediction`, `ml_valid`
- Raw Python output is never exposed in the final result
- Modified `app/Services/RiskAssessmentService.php` — injected `DecisionIntegrationService`, replaced all inline response construction with orchestrator-only pattern
- RiskAssessmentService now delegates all five response paths to `DecisionIntegrationService`
- Created `tests/Unit/Services/DecisionIntegrationServiceTest.php` — 15 tests, 68 assertions

Design defense: DecisionIntegrationService centralizes the approved safety hierarchy. It ensures that missing records stop classification, deterministic clinical rules override machine learning, and invalid ML output never defaults to LOW.

UI documentation: No Blade or frontend files changed in Sprint 5. The new metadata keys (decision_source, missing_records, rule_reasons, ml_prediction, ml_valid) prepare the future Explainability UI to show decision source, missing requirements, deterministic reasons, ML contribution, and final decision path. Raw Python output and technical exceptions must never be shown directly to clinic users.

## Known Issues

- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0
- ASSESSMENT INCOMPLETE may still appear as Low in an obsolete Blade branch
- Referral feature test has a pre-existing 403 authorization failure
- Other known pre-existing test failures remain unrelated to CDSS extraction
- `previous_cs` and `miscarriage` columns are present in the Patient model's fillable array but absent from all database migrations — they exist in the production database but cause test failures on fresh in-memory SQLite

## Sprint 6B — Explainability UI Correction and Refinement

Status: Complete

### Changes

**Controller fix — `app/Http/Controllers/PrenatalVisitController.php`**

Removed `json_encode()` calls for fields that the `PrenatalVisit` model casts as arrays:
- `store()`: `risk_reasons`, `missing_records`, `rule_reasons` now assigned as raw arrays
- `update()`: same three fields corrected
- `recalculateIncompleteVisits()`: same three fields corrected
- Initial `create()` call also corrected (`risk_reasons` as `[]` instead of `json_encode([])`)

The model's `$casts` (`risk_reasons => array`, `missing_records => array`, `rule_reasons => array`) handles JSON serialization automatically.

**View fix — `resources/views/patients/show.blade.php`**

**Visit table badge (left column):** Explicit branches for all known risk levels:
- HIGH → High (red)
- LOW → Low (green)
- ASSESSMENT INCOMPLETE → Assessment Incomplete (amber)
- otherwise → Unknown (gray)

No longer mislabels ASSESSMENT INCOMPLETE as Low.

**Visit details decision label:** Uses full labels (Completeness Check, Clinical Rules, Machine Learning, ML Assessment Unavailable) with a neutral gray fallback for null legacy records.

**Risk Assessment Card (right sidebar):**

Card background and badge colors follow the correct scheme:
- HIGH → red
- LOW → green  
- ASSESSMENT INCOMPLETE → amber
- otherwise → gray

Decision source mapping:
- COMPLETENESS → Completeness Check
- RULE_BASED → Clinical Rules
- MACHINE_LEARNING → Machine Learning
- MACHINE_LEARNING_INVALID → ML Assessment Unavailable
- null → Legacy assessment — explanation metadata unavailable

Conditional sections per decision path:

- **COMPLETENESS**: Decision Source, Missing Records, ML note ("Not executed because required information was incomplete"), Clinical Assessment, Recommendation & Next Steps
- **RULE_BASED**: Decision Source, Triggered Rules, ML note ("Not executed because deterministic clinical safety rules already established HIGH"), Risk Factors (only when HIGH), Clinical Assessment, Recommendation & Next Steps
- **MACHINE_LEARNING**: Decision Source, Deterministic Rules note ("No HIGH-risk rule was triggered"), ML Assessment (prediction + validation status + contribution wording), Clinical Assessment, Recommendation & Next Steps
- **MACHINE_LEARNING_INVALID**: Decision Source, Required Records complete, Deterministic Rules ("No HIGH-risk rule was triggered"), ML Assessment unavailable, Clinical Assessment, Recommendation & Next Steps
- **null (legacy)**: Decision Source (gray "Legacy Assessment"), Clinical Assessment, Recommendation & Next Steps

Wording compliance:
- No use of "diagnosis", "confirmed disease", or "system decided"
- Uses "clinical assessment", "decision support", "system-generated"
- No raw Python errors, commands, logs, tracebacks, or raw output

**Records modified:**
- `app/Http/Controllers/PrenatalVisitController.php` — 10 `json_encode()` calls replaced with direct array assignment
- `resources/views/patients/show.blade.php` — visit badge, detail labels, and full Risk Assessment Card redesign
- `docs/IMPLEMENTATION_PROGRESS.md` — this entry

**Records NOT modified (unchanged):**
- Routes, migrations, models, services (DecisionIntegrationService, RiskAssessmentService, ClinicalRuleEngine, CompletenessValidator, MachineLearningService), Python files, other Blade views, other controllers

### Test Results
- PHP syntax check: clean
- **46 unit tests pass** (all pre-existing, none modified)

### Known Issues
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0
- Referral feature test has a pre-existing 403 authorization failure
- `previous_cs` and `miscarriage` columns missing from migrations but present in production database

## Sprint 7 — Explainability Across the System

Status: Complete

### Objective

Extend the approved explainability language and visual design from the patient profile to the Dashboard, Risk Monitoring page, and printable clinical assessment output.

### Part A — Dashboard

**Controller changes — `app/Http/Controllers/DashboardController.php`**

- Added `latestVisitSubquery()` helper that returns a subquery (`SELECT MAX(id) ... GROUP BY patient_id`) reusable across all queries
- Added `countLatestByRisk()` helper for counting patients whose latest visit has a given risk level
- Fixed `$highRisk`, `$lowRisk` counts to use latest-visit-per-patient instead of all-visits
- Added `$incompleteCount`, `$incompletePatients`, `$overdueCount`, `$overdueFollowUps`
- Staff dashboard: fixed `$highRiskAlerts` to use latest-visit-per-patient subquery; added `$staffHighRiskCount`, `$staffLowRiskCount`, `$staffIncompleteCount`

**View changes — `resources/views/dashboards/admin.blade.php`**

- Added explainable risk summary card row (HIGH, LOW, ASSESSMENT INCOMPLETE, Follow-ups Overdue) between the KPI row and Priority Monitoring section
- Each card displays: count, staff-facing explanation, and link to filtered Risk Monitoring view
- Enhanced Priority Monitoring list to show decision-source label, evidence reasons (rule_reasons / missing_records / ML prediction), next-visit label, and overdue status per patient

**View changes — `resources/views/dashboards/staff.blade.php`**

- Added explainable risk summary row with three cards (HIGH, ASSESSMENT INCOMPLETE, LOW) above the KPI row
- Enhanced Priority Alerts list to show decision-source label and first evidence reason per patient

### Part B — Risk Monitoring

**Controller changes — `app/Http/Controllers/RiskMonitoringController.php`**

- Rewrote `index()` to show the latest prenatal visit per patient (not all visits)
- Added `ASSESSMENT INCOMPLETE` to the `risk_filter` dropdown options
- Added `decision_source` filter supporting COMPLETENESS, RULE_BASED, MACHINE_LEARNING, MACHINE_LEARNING_INVALID
- All summary counts (highRiskCount, lowRiskCount, incompleteCount) use latest-visit-per-patient queries
- Changed paginated variable name from `$highRiskVisits` to `$visits`

**View changes — `resources/views/risk/monitoring.blade.php`**

- Replaced the two-card summary with three explainable cards (HIGH, LOW, ASSESSMENT INCOMPLETE) showing count, explanation, and filter link
- Added decision-source dropdown filter alongside existing risk-level filter
- Replaced "Risk Statistics Overview" with 4-column summary (Total / HIGH / LOW / Incomplete)
- Replaced "High Risk Patient Registry" table with unified "Patient Assessments" table showing all risk levels
- Each row shows: patient name, risk-level badge (red/green/amber/gray), decision-source label (color-coded), evidence summary (rule_reasons, missing_records, ML prediction with "+ N more"), last visit, next visit / monitoring label, and action link
- Decision-source labels: Completeness Check (amber), Clinical Rules (orange), Machine Learning (blue), ML Assessment Unavailable (gray), Legacy Assessment (neutral gray)
- Evidence summary rules per decision source:
  - RULE_BASED: up to two rule_reasons + "+ N more" if applicable
  - COMPLETENESS: up to two missing_records + "+ N more"
  - MACHINE_LEARNING: "Prediction: [HIGH/LOW] (Valid)" + "No HIGH-risk rule triggered"
  - MACHINE_LEARNING_INVALID: "Model assessment unavailable" + "No HIGH-risk rule triggered; model did not produce valid result"
  - Legacy: "Legacy assessment — explanation metadata unavailable"
- Updated system description card with approved clinical decision support language
- Removed separate LOW risk collapsible section (all levels now unified)
- Removed old export modals and CSV export button

### Part C — Printable Clinical Assessment

**View changes — `resources/views/exports/patient-record.blade.php`**

- Replaced section 4 (Prenatal Visit) and section 5 (Risk Monitoring Summary) with a comprehensive "Clinical Decision Summary" section
- Includes:
  1. Final Risk Assessment
  2. Decision Source
  3. Triggered Clinical Rules (when RULE_BASED)
  4. Missing Required Records (when COMPLETENESS)
  5. Machine-Learning Contribution (when MACHINE_LEARNING)
  6. Decision Path explanation per source
  7. Clinical Assessment text
  8. Recommended Action
  9. Recommended Follow-up date
  10. Safety disclaimer: "This system-generated assessment is intended to support clinical decision-making and is not a medical diagnosis. Final clinical judgment remains with qualified clinic personnel."
- Decision-path wording per specification (RULE_BASED, COMPLETENESS, MACHINE_LEARNING, MACHINE_LEARNING_INVALID, Legacy)

### Decision-Source Wording (Applied Consistently)

| Code | Label | Color |
|------|-------|-------|
| COMPLETENESS | Completeness Check | Amber |
| RULE_BASED | Clinical Rules | Orange |
| MACHINE_LEARNING | Machine Learning | Blue |
| MACHINE_LEARNING_INVALID | ML Assessment Unavailable | Gray |
| null | Legacy Assessment | Neutral Gray |

### Risk-Level Badge Colors

| Level | Badge Color |
|-------|------------|
| HIGH | Red |
| LOW | Green |
| ASSESSMENT INCOMPLETE | Amber |
| otherwise | Gray |

### Clinical Language Compliance

- No use of "diagnosis", "confirmed disease", "system decided", "AI decided", "model proved", etc.
- Uses "clinical assessment", "decision source", "triggered clinical rules", "machine-learning contribution", "supports clinical decision-making", "system-generated", "not a medical diagnosis"
- No raw ML output, Python commands, tracebacks, exception details, model file paths, or technical logs

### Query and Performance Changes

- All dashboard and Risk Monitoring queries now use subquery pattern: `SELECT MAX(id) ... GROUP BY patient_id` to get latest visit per patient
- Eager loading via `with('patient')` preserved on all queries
- No N+1 queries introduced
- No schema changes or new migrations
- No denormalized counters

### Tests

Created `tests/Feature/ExplainabilitySprint7Test.php` — 14 tests, 22 assertions:

1. Dashboard HIGH count uses latest visit per patient
2. Dashboard LOW count uses latest visit per patient
3. Dashboard INCOMPLETE count uses latest visit per patient
4. Patient with multiple visits counted only once on dashboard
5. Risk Monitoring shows RULE_BASED explanations
6. Risk Monitoring shows COMPLETENESS missing records
7. Risk Monitoring shows MACHINE_LEARNING prediction and validity
8. Risk Monitoring shows MACHINE_LEARNING_INVALID safely
9. Legacy assessments use neutral fallback text
10. ASSESSMENT INCOMPLETE never renders as LOW or green
11. Delivered and referred monitoring labels remain unchanged
12. Risk Monitoring filter accepts ASSESSMENT INCOMPLETE
13. Risk Monitoring filter accepts decision_source
14. Existing patient-profile explainability still works

### Test Results

- PHP syntax check: clean (all changed PHP files)
- **79 tests pass** (14 new + 65 pre-existing)
- **18 pre-existing failures** (all CSRF/419 unrelated — same as Sprint 6B)
- Zero regressions

### Records Modified

- `app/Http/Controllers/DashboardController.php` — queries, helpers, view data
- `app/Http/Controllers/RiskMonitoringController.php` — queries, filters, view data
- `resources/views/dashboards/admin.blade.php` — explainable cards, enhanced priority list
- `resources/views/dashboards/staff.blade.php` — explainable cards, enhanced alerts
- `resources/views/risk/monitoring.blade.php` — unified table with decision source + evidence
- `resources/views/exports/patient-record.blade.php` — Clinical Decision Summary
- `tests/Feature/ExplainabilitySprint7Test.php` — 14 new tests
- `docs/IMPLEMENTATION_PROGRESS.md` — this entry

### Records NOT Modified

- Routes, migrations, models, services (DecisionIntegrationService, RiskAssessmentService, ClinicalRuleEngine, CompletenessValidator, MachineLearningService), Python files, PrenatalVisitController, PatientController, other Blade views, clinical thresholds, decision hierarchy, assessment logic

### Known Issues

- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0
- Referral feature test has pre-existing 403/419 authorization failure
- `previous_cs` and `miscarriage` columns missing from migrations but present in production database
- `recommendation` column exists in PrenatalVisit $fillable but has no migration (present in production DB)

### Defense Note

Explainability is applied consistently across point-of-care, monitoring, and printed communication. The patient profile provides the full explanation, the Dashboard prioritizes attention, Risk Monitoring supports rapid review, and the printable report preserves decision traceability during referral or record review.

## Sprint 7 Correctness Patch — Strict Non-Destructive Mode

Status: Complete

### Objective

Correct Sprint 7 implementation issues without changing clinical assessment behavior. Six patches applied as specified.

### Patch 1 — Correct Latest-Assessment Queries

**Issue:** `DashboardController::latestVisitSubquery()` accepted `$riskLevel` and filtered by `risk_level` INSIDE the `MAX(id)` subquery. This meant if a patient had an old HIGH visit (id=1) and a new LOW visit (id=2), `countLatestByRisk('HIGH')` would find `MAX(id)=1 WHERE risk_level='HIGH'` and wrongly count that patient as HIGH.

**Fix:** Removed `$riskLevel` parameter entirely. The subquery now unconditionally selects `MAX(id)` per patient. `countLatestByRisk()` applies risk_level filter in the outer `PrenatalVisit` query only. Both `$highRiskPatients` and `$incompletePatients` queries also use the unfiltered subquery now.

**Files:** `app/Http/Controllers/DashboardController.php`

### Patch 2 — Admin vs Staff Data Scope

**Issue:** Staff dashboard showed clinic-wide data instead of only the logged-in staff's assigned patients. The `assigned_staff_id` column existed on `patients` table but was unused in all staff queries.

**Fix:** All staff dashboard queries now scope via `where('assigned_staff_id', auth()->id())` or `whereHas('patient', fn($q) => $q->where('assigned_staff_id', $staffId))`:

- Patients Today / Appointments Today / Pending Checkups
- Staff HIGH / LOW / INCOMPLETE risk counts
- Staff HIGH alerts list
- Upcoming Appointments
- Follow-up Tasks
- Total Patients / Active Patients
- Recent Visits

Admin dashboard remains clinic-wide and unchanged.

**Files:** `app/Http/Controllers/DashboardController.php`

### Patch 3 — Soft-Delete Safety

**Issue:** No `whereNull('deleted_at')` on any latest-visit subquery. A soft-deleted visit with the highest `id` would be treated as the patient's current assessment, hiding the actual latest non-deleted visit.

**Fix:** Added `->whereNull('deleted_at')` to:
- `DashboardController::latestVisitSubquery()`
- `RiskMonitoringController::latestVisitSubquery()`
- Dashboard overdue follow-ups subquery

**Files:** `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/RiskMonitoringController.php`

### Patch 4 — Preserve Existing Export Features

**Status:** No action needed. Git history investigation confirmed that no CSV export controls, export modals, or export-related functionality existed in `resources/views/risk/monitoring.blade.php` before Sprint 7. The old Sprint 7 documentation entry that said "Removed old export modals and CSV export button" was inaccurate — those features were never part of the committed codebase.

### Patch 5 — Printable Report Null Safety

**Issue:** `resources/views/exports/patient-record.blade.php` section 3 (Medical History) accessed `$history->property` directly without checking whether `$patient->medicalHistory` was null. A patient without Medical History would crash the template.

**Fix:** Wrapped the Medical History table in `@if($history)` guard. Added `@else` branch displaying "No medical history recorded." per specification.

**Files:** `resources/views/exports/patient-record.blade.php`

### Patch 6 — Strengthen Tests

**Old test count:** 14 tests (mostly `assertOk()` only)
**New test count:** 21 tests with 67 specific assertions

New/strengthened tests:

1. Old HIGH then latest LOW — HIGH count=0, LOW count=1 via `data-testid`
2. Old LOW then latest INCOMPLETE — LOW count=0, INCOMPLETE count=1 via `data-testid`
3. Patient with multiple visits appears once
4. Soft-deleted newest visit — latest non-deleted remains current
5. Staff dashboard shows only assigned patients — exact count via `data-testid`
6. Admin dashboard shows patients regardless of staff assignment
7. RULE_BASED shows "Clinical Rules" label + rule reason text
8. COMPLETENESS shows "Completeness Check" label + missing record text
9. MACHINE_LEARNING shows "Prediction" + "Valid" text
10. MACHINE_LEARNING_INVALID shows "ML Assessment Unavailable" + no "Traceback" / "raw_output"
11. ASSESSMENT INCOMPLETE labelled "INCOMPLETE", never "LOW"
12. Delivered label remains "Delivered"
13. Referred label remains "Referred"
14. Risk level filter excludes non-matching patient names
15. Decision source filter shows/hides correctly for RULE_BASED and MACHINE_LEARNING (replaced misleading "multiple values" test)
16. ASSESSMENT INCOMPLETE filter accepted
17. Raw fields (raw_output, parsed_output, Traceback, Python) never rendered
18. Printable report includes Decision Source and safety disclaimer (download route, `assertOk()`)
19. Printable report renders with fallback text when Medical History is absent (Blade view directly, not PDF binary)
20. Existing patient-profile explainability still works
21. (Removed: 422 test — controller guard removed per Patch 5)

### Patch 6b — Exact Count Assertions via `data-testid`

Added `data-testid` attributes to all six risk count elements across both admin and staff dashboards:
- `admin-high-count`, `admin-low-count`, `admin-incomplete-count`
- `staff-high-count`, `staff-low-count`, `staff-incomplete-count`

Tests assert exact numeric values using `assertTestIdCount()` helper that extracts the count value from the HTML element containing the matching `data-testid` attribute.

**Files:** `resources/views/dashboards/admin.blade.php`, `resources/views/dashboards/staff.blade.php`

### Patch 6c — Test Name and Assertion Cleanup

- Renamed "risk monitoring filter accepts multiple decision_source values" → replaced with actual matching/nonmatching assertions for `RULE_BASED` and `MACHINE_LEARNING`
- Printable report test: Decision Source wording now verified via `view('exports.patient-record', ...)->render()` to avoid PDF binary assertion limitation

### Patch 6d — Printable Report without Medical History

Created test "printable report renders with empty Medical History" that:
- Creates a patient with `philhealth_member = false` and **no** `MedicalHistory`
- Downloads via `route('patients.download', ...)` with `format=pdf`, asserts `assertOk()`
- Renders `exports.patient-record` Blade view directly, verifies "No medical history recorded." fallback text and "Decision Source" heading appear

**Test Results**

- PHP syntax: clean (all changed PHP files)
- 21 Sprint 7 tests: **all pass** (67 assertions)
- Full suite: **100 pass** (up from 79 pre-patch — remaining 4 are pre-existing failures)
- Pre-existing failures: **4** (ExampleTest guest redirect, PatientPhilhealthTest 403, ProfileTest soft-delete, RiskMonitoringStatusTest 403) — zero new regressions
- Testing database: in-memory SQLite (`:memory:`) confirmed isolated

### Records Modified

- `app/Http/Controllers/DashboardController.php` — latestVisitSubquery rewrite, staff scoping, soft-delete
- `app/Http/Controllers/RiskMonitoringController.php` — soft-delete in subquery
- `resources/views/exports/patient-record.blade.php` — Medical History null guard
- `resources/views/dashboards/admin.blade.php` — data-testid on risk count elements
- `resources/views/dashboards/staff.blade.php` — data-testid on risk count elements
- `tests/Feature/ExplainabilitySprint7Test.php` — 21 tests (up from 14), 67 assertions (up from 22)
- `docs/IMPLEMENTATION_PROGRESS.md` — this entry

### Records NOT Modified

- No migrations created or executed
- No database commands executed (migrate:fresh, migrate:reset, etc.)
- No clinical services modified
- No Python files modified
- No routes modified
- No models modified (PrenatalVisit, Patient, User)
- No clinical thresholds or assessment hierarchy
- No .env files

### Confirmation

- No migration created or executed
- No production database command executed
- No clinical logic changed
- No Python files changed
- No routes changed
- No authentication or authorization rules changed
- Testing performed only on isolated in-memory SQLite

### Known Issues

- Pre-existing: `recommendation` column in PrenatalVisit $fillable but missing from migrations (causes SQLite test crash if used in `::create()`)
- Pre-existing: `previous_cs` and `miscarriage` in Patient $fillable but missing from migrations
- Pre-existing: Referral feature test has 403 authorization failure
- Pre-existing: ProfileTest soft-delete mismatch
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0

## Sprint 8 — Structured Assessment Result Object

Status: Complete

### Part A — Initial Implementation

**Created:**
- `app/ValueObjects/AssessmentResult.php` — immutable value object with 10 typed readonly properties

**Modified:**
- `app/Services/DecisionIntegrationService.php` — `decide()` returns `AssessmentResult` instead of `array`; `buildResponse()` removed; all five decision paths use `new AssessmentResult(...)` with named arguments
- `app/Services/RiskAssessmentService.php` — `assess()` return type changed to `AssessmentResult`
- `tests/Unit/Services/DecisionIntegrationServiceTest.php` — three tests adapted from `toHaveKey()` to property-based assertions

### Part B — Hardening Patch (Sprint 8 Final)

**1. CarbonImmutable nextVisit**

`nextVisit` is now typed as `Carbon\CarbonImmutable` instead of `DateTimeInterface`. All `DecisionIntegrationService` paths construct immutable dates via `now()->toImmutable()->addDays(N)`. The same day offsets are preserved (30 for default/COMPLETENESS/ML_INVALID, 3 for HIGH risk paths). Controllers call `$riskAssessment['nextVisit']->toDateString()` via `ArrayAccess`, which works identically on `CarbonImmutable`.

**2. ArrayAccess hardening**

`offsetExists()` returns `false` for non-string offsets; returns `true` only for the ten approved keys. `offsetGet()` returns the corresponding property for valid keys and throws `OutOfBoundsException` for unknown or non-string offsets (previously returned `null` silently). `offsetSet()` and `offsetUnset()` continue throwing `LogicException`. The approved key list is defined as a private constant `APPROVED_KEYS`.

**3. Dedicated value-object tests**

Created `tests/Unit/ValueObjects/AssessmentResultTest.php` with 10 tests, 28 assertions:
1. All ten typed properties are exposed
2. `toArray()` contains exactly the ten approved keys
3. Property access and array access return identical values
4. Unknown key access throws `OutOfBoundsException`
5. Array assignment throws `LogicException`
6. Array unset throws `LogicException`
7. `nextVisit` is `CarbonImmutable`
8. Immutable date operation returns a new instance without altering the original
9. Every `DecisionIntegrationService` path returns an `AssessmentResult`
10. Serialization does not expose `raw_output` or `parsed_output`

### Serialization Contract

`toArray()` is the explicit serialization contract. It returns exactly the ten approved keys with their typed values. Controllers currently retain array-style reads through `ArrayAccess` (e.g. `$riskAssessment['risk_level']`) rather than calling `toArray()`. The `toArray()` method is verified in tests to contain (and be limited to) the ten approved fields and to exclude `raw_output` and `parsed_output`.

### Files NOT Modified

- Controllers (PrenatalVisitController, BirthPlanController, MedicalHistoryController, UltrasoundController) — continue using `$result['key']` syntax via `ArrayAccess`; not migrated to `toArray()`
- All Blade views — untouched
- ClinicalRuleEngine, CompletenessValidator, MachineLearningService — untouched
- Models, migrations, routes, Python files, configuration — untouched
- No database commands executed

### Design Defense

The associative array pattern had four drawbacks: no type safety, no discoverability, mutability, and no serialization contract. `AssessmentResult` solves all four with typed readonly properties, `ArrayAccess` for transitional backward compatibility, `CarbonImmutable` for date immutability, and `toArray()` as a single serialization contract. The strict `offsetGet` (throwing `OutOfBoundsException` on misspelled keys) prevents silent null propagation that would be invisible in the old array implementation.

### Clinical Safety

- No risk factor, threshold, decision hierarchy, or recommendation text changed
- All assessment wording character-for-character identical
- No database schema or migration touched

### Test Results

- PHP syntax check: clean on all changed PHP files
- **56 unit tests pass** (46 from Sprint 7 + 10 new AssessmentResult tests)
- **110 tests pass** in full suite (same 4 pre-existing failures)
- Zero regressions
- DB: `sqlite` / `:memory:` confirmed

### Known Issues

- Pre-existing: `recommendation` column in PrenatalVisit $fillable but missing from migrations
- Pre-existing: `previous_cs` and `miscarriage` in Patient $fillable but missing from migrations
- Pre-existing: Referral feature test has 403 authorization failure
- Pre-existing: ProfileTest soft-delete mismatch
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0

## Next Planned Work

1. Sprint 9 �?" Create the Document-to-Code Clinical Factor Matrix.
2. Map Documents 0�?"7 against current database fields, forms, services, rules, explanations, and tests.
3. Do not add or change clinical rules until the matrix is reviewed and approved.
4. Keep EDD-triggered pregnancy outcome monitoring planned for a later dedicated sprint.

## Sprint 16 - Phase 16A - Read-Only Referral Inspection

Status: Complete (inspection only, no implementation)

Branch: feature/sprint-16-referral-follow-through

### Findings

- Referral table (`referrals`) held only manual workflow fields: `patient_id`, `created_by`, `referred_to`, `doctor_name`, `reason`, `notes`, `referral_date`, `status` (`Pending`/`Completed`/`Cancelled`), `waiver_signed` (schema-only, NOT in `$fillable`), `completed_at`.
- No `prenatal_visit_id`, no assessment snapshot, no urgency, no refusal fields, no outcome data, no soft deletes.
- `ReferralController` has `index`, `analytics`, `create`, `store`, `complete`, `print`. `store()` sets `patient.status = REFERRED` (architecturally coupled; intentionally left unchanged until Phase 16D). Delivered-patient guard is an inline `create()` check only; `store()` does not re-check.
- Referral cannot be linked to a specific assessment today: patient profile shows a single "Refer Patient" button for all ONGOING patients, no HIGH-specific entry.
- No duplicate-referral constraint exists; multiple referrals can already exist per patient.
- Assessment data available: `PrenatalVisit` persists `risk_level`, `assessment`, `recommendation`, `decision_source`, `missing_records`, `rule_reasons`, `ml_prediction`, `ml_valid`, `urgency`, `bp_assessment`, `factor_evidence`, and `assessment_metadata` (which carries `context`, `interaction_evidence`, `decision_trace`, `versions`, `assessed_at`). `assessed_at` and interaction evidence live only inside `assessment_metadata`.
- The existing column `waiver_signed` is present in the schema but was missing from the model's `$fillable` (confirmed mismatch).

## Sprint 16 - Phase 16B - Referral Schema & Snapshot Contract Foundation

Status: Complete (schema/model foundation; migration created but NOT executed)

### Scope

Additive referral-integration schema foundation only. No referral creation workflow, no UI changes, no refusal actions, no `patient.status = REFERRED` behavior change, no clinical logic touched (rules stay 1.1.0).

### Migration (created, NOT run)

`database/migrations/2026_08_09_000001_add_referral_integration_to_referrals_table.php`

- `prenatal_visit_id` nullable FK to `prenatal_visits` with `nullOnDelete`. Historical referral evidence is not destroyed when a visit is soft-deleted or hard-removed.
- `assessment_snapshot` nullable JSON (the immutable persisted-evidence copy written once at referral creation, later).
- `refusal_recorded_at` nullable timestamp.
- `refusal_recorded_by` nullable FK to `users` with `nullOnDelete` (removing a user never destroys referral history).
- `refusal_notes` nullable text.
- `status` enum extended to `Pending | Completed | Cancelled | Refused` (existing values preserved).
- `waiver_signed` already exists; was mapped into `$fillable` (the Phase 16A mismatch fix). No new waiver column.
- No backfill, no UPDATE of existing rows, no historical rewrite. Migration left Pending in the developer DB.

### Rollback strategy

Columns roll back via guarded `hasColumn()` drops, FKs are dropped before columns, and the status enum is reverted to the original three values. Non-destructive and data-protective; no no-op `down()`.

### Rollback safety correction

`down()` now calls `assertNoRefusedRows()` BEFORE any destructive operation. If any referral currently uses `status = 'Refused'`, rollback aborts with a `RuntimeException`, no row is converted/deleted/coerced, and the Phase 16B schema is left fully intact (no partial rollback). When no `Refused` rows exist the enum narrows to `Pending | Completed | Cancelled` and the columns drop normally. Covered by `tests/Feature/ReferralMigrationRollbackSafetyTest.php` (4 tests, 24 assertions).

### Model / relationships

- `Referral::$fillable`: added `prenatal_visit_id`, `assessment_snapshot`, `waiver_signed`, `refusal_recorded_at`, `refusal_recorded_by`, `refusal_notes`.
- `Referral::$casts`: `assessment_snapshot` => array, `waiver_signed` => boolean, `refusal_recorded_at` => datetime (existing date/datetime preserved).
- New relationships: `Referral::prenatalVisit()`, `Referral::refusalRecordedBy()`, `PrenatalVisit::referrals()`. `patient()` and `user()` preserved; `Patient::referrals()` untouched.

### Snapshot service (created)

`app/Services/ReferralAssessmentSnapshotService.php` — `fromPrenatalVisit(PrenatalVisit $visit): ?array` returns the immutable snapshot from persisted assessment only.

- Approved keys only: `schema_version, prenatal_visit_id, visit_date, risk_level, decision_source, urgency, assessment, recommendation, rule_reasons, factor_evidence, interaction_evidence, bp_assessment, assessment_date, assessed_at, versions`.
- `interaction_evidence` copied from persisted `assessment_metadata.interaction_evidence` and normalized with `ClinicalInteractionEvidence::normalizeList` (not ACTIVE-status dependent).
- `factor_evidence` normalized with `ClinicalFactorEvidence::normalizeList`.
- No `RiskAssessmentService`/BP/ML invocation, no new ultrasound queries, no Eloquent serialization, no PII, no arbitrary notes.
- Returns `null` when the visit carries no persisted assessment (legacy safety).

### Immutability principle

The snapshot is intended to be written once at assessment-linked referral creation. No observers/listeners auto-update it; a later visit/ultrasound/registry/version change must never regenerate the stored snapshot.

### Tests

`tests/Feature/ReferralIntegrationSchemaTest.php` (13 tests, 38 assertions) covering: column presence, `Refused` status, `waiver_signed` mapping, legacy-null referral, snapshot array cast, all three new relationships, and the snapshot contract (approved keys, interaction copy from metadata, versions/assessed_at copy, no PII, null-when-no-assessment).

- Focused: 13 passed.
- Referral regression (`ReferralAnalyticsTest`, `RiskMonitoringStatusTest`): 37 passed + 1 pre-existing unrelated 403 failure (unchanged from Sprint 15 baseline).
- Sprint 15 assessment regression: 58 passed.
- Full suite: 460 passed, 3 failed (same 3 pre-existing unrelated failures: ExampleTest guest redirect, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403).

### Scope confirmation

Migration created but NOT executed (`migrate:status` shows the new file as Pending). No clinical logic, BP, ML, completeness, interactions, patient `REFERRED` behavior, referral creation UI, or refusal action changed. No EDD/outcome work. Nothing committed/pushed.

## Sprint 16 - Phase 16C - Assessment-Linked Referral Creation & Immutable Evidence Snapshot

Status: Complete (assessment-linked referral creation shipped; no migration, no refusal workflow)

Branch: feature/sprint-16-referral-follow-through

> The Phase 16B migration was subsequently executed on the developer MySQL database by the human reviewer (`migrate:status` shows `2026_08_09_000001_add_referral_integration_to_referrals_table` as `[21] Ran`). Phase 16C added NO new migration.

### Objective

Let staff deliberately create a referral from a SPECIFIC HIGH-risk prenatal assessment, linking `referral.prenatal_visit_id` and freezing a server-built `assessment_snapshot` (historical evidence). Manual/legacy referral creation is preserved unchanged. No refusal workflow, no `patient.status = REFERRED` decoupling, no migration, no commit/push.

### Approach

Reused the existing routes — `GET /patients/{id}/referral/create` (`referrals.create`) and `POST /referrals/store` (`referrals.store`) — no route changes. The linked mode is entered through the HIGH assessment card on `patients/show.blade.php` ("Create Referral from this Assessment"), which passes a `prenatal_visit_id` query parameter.

### Controller changes (`app/Http/Controllers/ReferralController.php`)

- Constructor now also receives `ReferralAssessmentSnapshotService` (still container-resolved; the legacy `ReferralAnalyticsService` injection is untouched).
- `create()`:
  - With `prenatal_visit_id`: the visit must exist, belong to the requested patient, not be soft-deleted, and carry `risk_level === 'HIGH'`. Otherwise `404` (missing/soft-deleted) or `403` (wrong patient / non-HIGH). No "latest visit" fallback — the referral is bound to the exact assessment chosen by staff.
  - The immutable `assessment_snapshot` is built for the preview from persisted evidence only (no assessment re-run, no BP/ML invocation).
  - A readable `reasonPrefill` is passed to the view so staff get a helpful starting reason.
  - The existing delivered-patient redirect guard is preserved.
- `store()` now closes the Phase 16A gap (store-side delivered guard) for BOTH modes, and adds the linked branch:
  - The visit is reloaded at save time (TOCTOU protection); must exist, not be soft-deleted, belong to the submitted patient, and be `HIGH` — otherwise rejected with a field error and nothing is created.
  - `assessment_snapshot` is always rebuilt server-side via the service; client-submitted `assessment_snapshot`, `created_by`, and `status` are never trusted (forgery test M).
  - A second `Pending` referral for the same `prenatal_visit_id` is blocked with a field error (no DB unique constraint). `Completed/Cancelled` referrals do NOT block later re-referral.
  - Persisted fields: `patient_id`, `prenatal_visit_id` (nullable -> manual stays null), server-built `assessment_snapshot`, `created_by`, `referred_to`, `doctor_name`, `reason`, `notes`, `referral_date`, `status = 'Pending'`.
  - Manual/legacy flow (no `prenatal_visit_id`) keeps its prior behavior, including `patient.status = REFERRED`.
  - Audit log description now reads: `Created referral #X for patient: <name> linked to PrenatalVisit #Z` for linked referrals (no snapshot dump). The existing `UPDATE` audit at `complete()` is unchanged.

### Snapshot prefill helper

`ReferralAssessmentSnapshotService::prefillReason(array $snapshot): string` builds an editable, clinician-readable reason: interaction labels → factor labels → rule-reason labels → persisted recommendation → persisted assessment. It never writes into the snapshot and never emits PII or raw developer codes.

### View changes

- `resources/views/patients/show.blade.php`: inside the Risk Assessment card, only when `risk_level === 'HIGH'` AND patient status is `ONGOING`, a red "Create Referral from this Assessment" button links to `referrals.create` with the latest HIGH assessment's id. LOW / ASSESSMENT INCOMPLETE / legacy-null patients show no linked button. The general manual "Refer Patient" header button is still present.
- `resources/views/referrals/create.blade.php`: linked mode renders a read-only "Linked Assessment Evidence (read-only)" panel (visit date, risk level, decision source, urgency with an "URGENT CLINICAL REVIEW" banner, factor labels, interaction labels, BP-URG note), a hidden `prenatal_visit_id`, and a reason textarea prefilled (editable). Manual mode renders as before.

### Tests

`tests/Feature/AssessmentLinkedReferralTest.php` (21 tests, 68 assertions): linked create page preview (risk HIGH, urgent banner, factors, interactions, reason prefill), LOW / ASSESSMENT INCOMPLETE / mismatched-patient / soft-deleted / delivered create rejection, persisted linked create, server-built snapshot with HIGH + urgency + BP-URG, factor/interaction preservation, mismatch/soft-delete/tamper/stale-form store rejection, delivered store rejection in both modes, duplicate-Pending block, closed-status re-referral allowed, manual flow preserved with null snapshot, snapshot immutability after later visit changes, audit CREATE with visit reference, profile button visibility (HIGH vs LOW).

### Verification

- Focused Phase 16C: 21 passed.
- Referral regression (`ReferralAnalyticsTest`, `RiskMonitoringStatusTest`): 14 passed + 1 pre-existing unrelated 403 (unchanged baseline).
- Phase 16B regression (`ReferralIntegrationSchemaTest`, `ReferralMigrationRollbackSafetyTest`): 17 passed.
- Full suite: 485 passed, 3 failed — the same 3 pre-existing unrelated failures as before (ExampleTest guest redirect 302/200, ProfileTest soft-delete account check, RiskMonitoringStatusTest referral 403 from `role = null` in `UserFactory`).
- `php -l` clean on all changed PHP files; `view:cache`/`view:clear` ok; `git diff --check` exit 0.

### Scope confirmation

No clinical thresholds, rules (stay 1.1.0), BP, ML, completeness, interactions, or assessment services were touched. No refusal workflow, no `patient.status = REFERRED` decoupling (temporary debt documented for Phase 16D), no audit redesign, no new migrations, nothing committed/pushed.

## Sprint 16 - Phase 16C Legacy-Assessment Safety Correction

Status: Complete (linkage now requires structured persisted assessment metadata)

Reviewed after human review. The assessment-linked referral path previously allowed a legacy `PrenatalVisit` with `risk_level = HIGH` but `assessment_metadata = null`, because the snapshot service only returned null when BOTH the risk was null AND metadata was empty. The linked path now requires a modern structured assessment.

### Eligibility rule (assessment-linked only)

1. `risk_level === 'HIGH'`
2. `assessment_metadata` is a non-empty array (structured Sprint 13+ persisted metadata)

No interaction evidence is required, no factor code is required, ML is not required, and no assessment is re-run. A modern HIGH with zero interactions remains eligible. A legacy HIGH with nullable metadata is NOT eligible for the linked path and stays reachable via the MANUAL referral workflow.

### Enforcement

- `ReferralController::create()`: after the HIGH-risk check, if `assessment_metadata` is not a non-empty array, `abort(422)` with: "This historical assessment does not contain structured evidence for an assessment-linked referral. Use the manual referral workflow instead."
- `ReferralController::store()`: same metadata guard (re-checked server-side, TOCTOU-safe) rejected with a form error on `prenatal_visit_id`; no referral is created.
- `patients/show.blade.php`: "Create Referral from this Assessment" now rendered only when the displayed assessment is HIGH AND `assessment_metadata` is a non-empty array. The general/manual `Refer Patient` action remains.

### Snapshot service

`ReferralAssessmentSnapshotService::fromPrenatalVisit()` now returns null whenever the visit lacks structured `assessment_metadata` (i.e. when `assessment_metadata` is not an array or is empty), because the service must represent a canonical structured referral snapshot. Risk level alone is no longer sufficient; no metadata is fabricated for legacy visits.

### Tests

`tests/Feature/AssessmentLinkedReferralTest.php` extended (29 tests, 95 assertions) proving: modern HIGH + structured metadata (create/store allowed), legacy HIGH + null metadata (create rejected 422, POST rejected, no referral created, profile button hidden), legacy HIGH manual referral still succeeds with null visit/snapshot, modern HIGH + metadata + zero interactions still succeeds, and LOW / INCOMPLETE linked behavior unchanged.

### Verification

- Focused Phase 16C: 29 passed.
- Referral regression (`ReferralAnalyticsTest`, `RiskMonitoringStatusTest`): 14 passed + 1 pre-existing unrelated 403 (unchanged baseline).
- Sprint 16B regression (`ReferralIntegrationSchemaTest`, `ReferralMigrationRollbackSafetyTest`): 17 passed.
- Full suite: 493 passed, 3 failed — same 3 pre-existing unrelated failures as before.
- `php -l` clean; `view:cache`/`view:clear` ok; `git diff --check` exit 0.

### Scope confirmation

Correction only. No migration (16B migration unchanged, still `[21] Ran`). No clinical assessment logic, BP, ML, completeness, or interaction rules changed. No refusal workflow. No `patient.status = REFERRED` change. No referral lifecycle/status change. Nothing committed/pushed.
## Sprint 16 - Phase 16D Referral Follow-Through, Refusal/Waiver & Pregnancy-Status Decoupling

Status: Complete (backend + UI + tests + docs done; REFERRAL UI/UX & PRINT FINALIZATION held for human review in Phase 16E)

### Approved scope

- Remove the `patient.status = REFERRED` write from referral creation.
- Implement dedicated referral follow-through transitions (Pending -> Completed / Refused / Cancelled) via `ReferralFollowThroughService`.
- Add refuse + cancel routes and usable UI.
- Decouple the pregnancy lifecycle (`patient.status` stays ONGOING) from the referral workflow state (`Referral.status`).
- Keep legacy `REFERRED`-status rows viewable without backfill.
- Do NOT begin Phase 16E. No migrations (16B migration remains `[21] Ran`). No EDD/outcome/BiPolar/assessment/BP/ML/completeness/interaction changes.

### Lifecycle decoupling decision

Creating a referral no longer writes `Patient.status = REFERRED`; referred pregnancies remain `ONGOING`. Referral progress is tracked exclusively on the `Referral` row (Pending -> Completed/Refused/Cancelled). Existing legacy `REFERRED` rows are left in place for viewing, treated as "ongoing pregnancy with referral activity", and are never silently rewritten to ONGOING.

### Transition model (`ReferralFollowThroughService`)

- New referral row always starts at `Pending`.
- `Pending -> Completed`: clinic-recorded follow-through/closed based on info available to the clinic. NOT electronic acceptance, admission, treatment done, or pregnancy end. Sets `completed_at`; refusal fields remain null.
- `Pending -> Refused`: patient declined. `refusal_notes` required (min 10 / max 2000); `refusal_recorded_at` = server now(); `refusal_recorded_by` = auth id; `waiver_signed` staff boolean (labeled "Physical referral-refusal waiver signed/recorded" - documentation only, no legal claims/digital signatures/uploads); `completed_at` stays null. Browser can NEVER forge timestamps/actor/status.
- `Pending -> Cancelled`: smallest clinic-side admin transition, distinct from refusal. Optional clean note reuses `referral.notes`. No invented medical reasons.
- Closed statuses (Completed/Refused/Cancelled) are terminal: cannot transition again or reopen to Pending; a new referral row preserves history (re-referral).
- Race protection: every transition reloads the row with `lockForUpdate()` inside a transaction.

### Service vs controller helper decision

A dedicated `ReferralFollowThroughService` was used (not a private controller helper) because the transition invariant (only Pending may move, server-stamped refusal metadata, locked read-modify-write) is shared by three routes, must behave identically under concurrency, and needs explicit unit-style coverage independent of HTTP. The controller stays a thin validator + audit wrapper, which is the pattern already used for `RiskAssessmentService` / `ReferralAnalyticsService`.

### Delivered guards

Strict read-only: delivered patients cannot have new referrals, and their existing referrals cannot be completed/refused/cancelled (complete/refuse/cancel return an error). No change without human approval. If real-life follow-through after delivery must be recorded, that is a separate policy decision for review.

### Audit

Successful transitions log an UPDATE in module REFERRAL with referral id + patient name + explicit transition (e.g. "Pending -> Completed"); refusal logs include waiver yes/no. Refusal narrative (`refusal_notes`) and assessment snapshots are never dumped. No audit row is written for blocked transitions.

### Monitoring presentation (PrenatalVisit)

Legacy REFERRED-status rows keep their historical "Referred" / not-overdue presentation. For ONGOING patients, a "Referred" indicator is shown only while an active Pending referral exists (via `Patient::hasActiveReferral()`), so a historical closed referral never permanently suppresses unrelated prenatal follow-up; once the referral closes, standard next-visit/overdue behavior resumes (delay of overdue is intentional while a referral is pending, matching the historical REFERRED semantics).

### Consumer inventory of `REFERRED`

- Removed: `ReferralController::store()` `'status' => 'REFERRED'` write (the core decoupling); `RiskMonitoringStatusTest` obsolete fixture + assertion `expect($patient->status)->toBe('REFERRED')` -> updated contract (patient stays ONGOING + referral Pending).
- Updated: `PrenatalVisit::getMonitoringNextVisitLabel()` / `isMonitoringOverdue()` now use `hasActiveReferral()` (active Pending) instead of coupling to the patient's REFERRED status; legacy REFERRED keeps its historical presentation.
- Unchanged / intentionally kept: `RiskMonitoringController` patient filter still includes REFERRED (legacy rows viewable); `ReferralAnalyticsTest` + `ExplainabilitySprint7Test` fixtures keep REFERRED patients for legacy-compatibility coverage.
- Refusal gates are bounded by refusals for delivered patients and non-Pending referrals.

### Routes

Added `POST /referrals/{id}/refuse` (`referrals.refuse`) and `POST /referrals/{id}/cancel` (`referrals.cancel`) beside existing `referrals.complete`, all under the staff middleware (auth + staff). Not accessible to admin (unchanged authorization).

### UI

- referrals/index: status filter now includes Refused + Cancelled; Refused/Cancelled status badges; 5 stat cards; Pending-only actions (Print / Complete / Record Refusal via modal / Cancel); Refused rows show recorded date + waiver flag.
- Patient profile: pending referral badge ("Pending Referral") rendered from `Patient::hasActiveReferral()` next to the name, independent of patient status.
- Referral print: shows completed-on and refusal/waiver details for closed rows.

### Tests (new)

`tests/Feature/ReferralFollowThroughTest.php` (18 tests): referral creation leaves patient ONGOING, complete/refuse/cancel transition math, closed referrals reject further transitions, refusal requires notes + server-stamps actor/time, browser cannot forge status fields, delivered patients can't transition, route validation, audit logging for transitions, monitoring indicator appears only while Pending and clears after closure, legacy REFERRED displays historically, follow-up overdue returns after a refusal closure.

`RiskTestingStatusTest` contract updated: referral creation -> referral Pending + patient stays ONGOING; staff fixture role fixed (the old UserFactory role-null 403 defect no longer applies to the store flow).

### Verification

- Focused 16D: 21 passed (ReferralFollowThroughTest + RiskMonitoringStatusTest).
- Related regression: AssessmentLinkedReferralTest (29), ReferralIntegrationSchemaTest (13), ReferralMigrationRollbackSafetyTest (4), ReferralAnalyticsTest (14), ExplainabilitySprint7Test (23), DeliveredPatientWorkflowTest (2), StaffAccessControlTest (8) -> 93 passed / 331 assertions.
- Sprint 15 regression (Sprint15InteractionUiTest): 15 passed.
- Full suite: 513 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 200; ProfileTest soft-delete mismatch). The prior RiskMonitoringStatusTest 403 is now fixed and its contract updated.
- Static: `php -l` clean on all touched files; `view:cache`/`view:clear` ok; `git diff --check` exit 0; `migrate:status` shows the 16B migration still `[21] Ran`.

### Scope confirmation

Hard scope respected: no migrations run or added (16B is still `[21] Ran`), no EDD/outcome work, no clinical assessment/BP/ML/completeness/interaction changes, no `REFERRED` backfill occurred, no commit/push. Phase 16E (Referral UI/UX & PRINT finalization) was NOT started.

## Sprint 16 - Phase 16D Human Review Correction

Status: Complete

Human review of Phase 16D requested three corrections. All implemented, tested, and documented. Phase 16E (Referral UI/UX & PRINT finalization) NOT started.

### Correction 1 - Pending referral no longer suppresses prenatal overdue

A Pending referral does NOT prove the receiving facility assumed care, that the patient attended, that clinic follow-up is cancelled, or that next_visit_date is moot. It is also patient-wide and could come from a manual referral unrelated to the specific visit.

Before: `PrenatalVisit::getMonitoringNextVisitLabel()` returned "Referred" and `isMonitoringOverdue()` returned false whenever the patient had any active Pending referral.

After: For new architecture patients (ONGOING + Referral.status = Pending):
- `getMonitoringNextVisitLabel()` returns the normal next-visit date (or "Not scheduled")
- `isMonitoringOverdue()` keeps the standard overdue calculation
- The referral is shown as a SEPARATE "Pending Referral" indicator in both the mobile card and desktop table views of risk monitoring.
- `Patient::hasActiveReferral()` is kept but ONLY as a UI indicator source; it is no longer a reason to return "Referred" / false.

So the UI can show BOTH "Overdue" and "Pending Referral".

### Correction 2 - Cancellation never overwrites original referral notes

`ReferralFollowThroughService::cancel()` no longer accepts or stores a cancellation note. It performs only the Pending -> Cancelled transition (completed_at null, refusal fields cleared). `referral.notes` captured at creation are NEVER overwritten. The controller dropped the `notes` validation/argument; the audit entry still records the transition. No new column, no migration.

### Correction 3 - Refusal wording

The refusal modal no longer says "`completed_at` is not set because the referral did not close." It now reads "`completed_at` is not set because the referral was refused rather than completed." No database semantics changed.

### Tests (Phase 16D human-review)

`tests/Feature/ReferralFollowThroughTest.php` (21 tests) + `tests/Feature/RiskMonitoringStatusTest.php` (3 tests) prove:

A. ONGOING + Pending referral + past next_visit_date -> still Overdue (keeps normal prenatal overdue behavior despite a pending referral)
B. same scenario -> Pending Referral indicator displayed separately
C. closing the referral does not change correctness of normal next-visit logic (no restore/overdue flip)
D. legacy patient.status = REFERRED -> historical Referred / not-overdue behavior remains
E. manual Pending referral does not suppress prenatal overdue
F. cancelling a referral with existing referral.notes -> original notes remain unchanged
G. cancellation route no longer overwrites notes (client cannot inject a cancellation narrative)
H. Pending -> Cancelled still works
I. refusal modal wording no longer says the referral "did not close"

### Verification

- Focused Phase 16D review: 24 passed / 93 assertions.
- Phase 16C/16B referral regression (AssessmentLinkedReferralTest 29, ReferralIntegrationSchemaTest 13, ReferralMigrationRollbackSafetyTest 4, ReferralAnalyticsTest 14, ExplainabilitySprint7Test 23): 83 passed / 301 assertions.
- Full suite: 516 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 200; ProfileTest soft-delete).
- `php -l` clean; `view:cache`/`view:clear` ok; `git diff --check` exit 0; `migrate:status` shows the 16B migration still `[21] Ran`; no migrations run.
- Nothing committed/pushed.

PHASE 16D HUMAN REVIEW CORRECTION COMPLETE — AWAITING FINAL REVIEW BEFORE PHASE 16E

## Sprint 16 - Phase 16E - Referral UI/UX, Historical Evidence Display & Print Finalization

Status: Complete (implementation + tests + docs; deferred to human acceptance)

Branch: feature/sprint-16-referral-follow-through

### Scope

Finalize the referral UI surface with a UI safety contract: views DISPLAY persisted/snapshot data only and never re-run any clinical engine (RiskAssessmentService, BloodPressureAssessmentService, ML, ClinicalInteractionEngine). No factors, interactions, BP thresholds, labels, eligibility, acceptance, or treatment completion are ever recomputed in Blade. The detail and print pages render from the frozen `Referral.assessment_snapshot` plus persisted relationships and value objects only. No migration was run in this phase (the 16B/16E schema `[21]` remains `Ran`).

### UI Decisions

- Dedicated detail page: new `GET /referrals/{id}` route (`referrals.show`) with `ReferralController::show($id)`, eager-loading `['patient','user','refusalRecordedBy','prenatalVisit']`. Manual referrals render a safe "Manual Referral" fallback with no invented evidence.
- Index enhanced with a Source column ("Assessment-linked" / "Manual Referral"), a new "View" action, explicit pending-only mutation buttons ("Mark Completed", "Record Refusal", "Cancel Referral"), refusal date + waiver sub-lines, and a status-filter dropdown. The shared refusal modal is rendered only when at least one Pending row is on the current page (prevents mutation text on all-closed pages).
- Status language: "Pending Referral" (amber), "Completed" (green), "Refused" (orange), "Cancelled" (gray). Never status-by-color alone; every badge carries a text label. Closed states render no mutation actions (backend routes remain authoritative and tested).
- Urgent banner is shown only when the snapshot `urgency === 'URGENT_CLINICAL_REVIEW'`.
- Friendly observed-context labels via `ClinicalFactorEvidence::displayObserved()` and `ClinicalInteractionEvidence::normalizeList()`: `ultrasound_inputs.amniotic_fluid` -> "Amniotic fluid", `ultrasound_inputs.presentation` -> "Fetal presentation".
- Decision-source friendly labels on the detail page: `RULE_BASED` -> "Rule-Based Clinical Assessment", `MACHINE_LEARNING` -> "Machine Learning Assessment", `COMPLETENESS` -> "Required Records Check", `MACHINE_LEARNING_INVALID` -> "ML Assessment Unavailable", fallback = raw value or "Legacy".
- No raw JSON / dict keys anywhere in UI or print (asserted O). LMP/EDD prints are null-guarded.

### Outcome / Refusal / Cancellation Panels

- **Refused**: shows recorded date + "Recorded by" (friendly `refusalRecordedBy` name; neutral "Staff account no longer available" fallback when the user is soft-deleted), refusal notes, and waiver status ("Physical waiver signed/recorded" or "Not recorded"). Exact wording: "`completed_at` is not set because the referral was refused rather than completed."
- **Completed**: shows `completed_at`. Wording: "Clinic staff recorded the referral follow-through as completed." No "accepted", "admitted", "treatment completed", "hospital accepted", "digitally signed", or "consent legally completed" vocabulary.
- **Cancelled**: shows Cancelled label only; `referral.notes` remain the original creation notes, never interpreted as a cancellation reason.

### Patient Profile (Phase 16C guards unchanged)

- "Create Referral from this Assessment" only when HIGH + structured `assessment_metadata` + guard conditions.
- When a Pending duplicate exists for that assessment, the button is replaced by a "Pending Referral Exists" link pointing to `referrals.show`. Duplicate POST remains protected server-side.
- New "Referrals" quick-card section (sorted by referral date/id) showing badge + destination + "X total · Y closed", with a side action link to the referrals index.

### Print (Phase 16E finalization)

- Header now reads "Print Date".
- Referral Details section includes Status, Referral State, and Source ("Assessment-linked"/"Manual Referral").
- Completed / Refused record sections with `completed_at` / refusal recorded date + recorder + waiver; neutral fallback for deleted recorders, cancelled section.
- Snapshot Evidence section rendered only from `assessment_snapshot` (urgency banner, risk level, assessment/visit dates, decision source, summary, recommendation, BP finding card, grouped clinical factor chips, interaction cards, version lines). Snapshot invariance is tested: a later live-visit change does NOT alter the printed evidence (test J).
- No faked details on manual-referral print (K test).

### Files

- `routes/web.php` — `referrals.show` in the `auth` group after the print route.
- `app/Http/Controllers/ReferralController.php` — `show()`, eager-loads on index/show/print.
- `app/Http/Controllers/PatientController.php` — eager-reloads `referrals` on show.
- `resources/views/referrals/show.blade.php` (new), `resources/views/referrals/index.blade.php`, `resources/views/referrals/print.blade.php`, `resources/views/patients/show.blade.php`.
- `tests/Feature/Phase16EUiTest.php` (24 tests).

### Tests

- Phase16EUiTest: 24 passed / 145 assertions (A-X).
- Focused regressions: ReferralFollowThroughTest + RiskMonitoringStatusTest + AssessmentLinkedReferralTest + ReferralIntegrationSchemaTest + ReferralMigrationRollbackSafetyTest + Phase16EUiTest = 94 passed; ReferralAnalyticsTest + PatientProfileRiskPanelTest + Sprint15InteractionUiTest + DeliveredPatientWorkflowTest + LegacyPatientShowRenderingTest + MedicalHistoryScopeTest = 66 passed.
- Full suite: 540 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 200; ProfileTest soft-delete mismatch).
- `php -l` clean on all changed PHP files; `view:clear` + `view:cache` clean; `git diff --check` exit 0; `migrate:status` confirms 16B/16E migration `[21] Ran`; no migrations run in this phase.

### Known notes / out of scope

- IMPLEMENTATION_PROGRESS.md from earlier phases lists "refusal metadata — `refusal_field`"; the actual persisted field is the boolean `waiver_signed` (see 16B schema contract), not a free-text `refusal_field`.
- Digital waiver signature (e.g. client-entered signature capture) is out of scope; the checkbox is documentation-only.
- Nothing committed/pushed.

PHASE 16E COMPLETE — AWAITING FINAL SPRINT 16 HUMAN ACCEPTANCE

## Sprint 16 - Phase 16E - Human UI/UX Review Correction (visual-only)

Status: Complete (visual/UX-only polish; no business logic, no migration, no Sprint 17)

Branch: feature/sprint-16-referral-follow-through (uncommitted)

### Scope & constraint confirmation

- VISUAL/UX ONLY. No referral logic, status transitions, eligibility, snapshot content, analytics, diagnosis/treatment claims, or DB schema changed. No new fields/statuses. No migrations run.
- `migrate:status` confirms the 16B/16E referral-integration migration remains `[21] Ran`; `git diff --check` exit 0.
- Follow-through mutations now live on the detail page only; the index no longer carries any mutation action.

### What changed (presentation only)

- `index.blade.php` — full Tailwind rewrite. Header subtitle now "Track referral decisions and clinical follow-through.". Summary hierarchy: amber "Action Required / Pending Referrals" leads; Completed/Refused/Cancelled and Total are quiet. Rows show patient, destination, date, reason (truncated), Source chip, status pill, and ONLY `[View Referral]` + `[Print]`. Mobile uses a `lg:hidden` card list; analytics panel moved below the operational table. Refused rows carry a small "Recorded M d" sub-line; completed rows show the completion date. Full refusal narrative lives on the detail page.
- `show.blade.php` (detail hub, order A-F): patient + destination header; three separately-coded compact status cards (Pregnancy ONGOING = sky, Referral status, Clinical Risk HIGH = red); Referral Information card; Assessment Evidence at Referral (neutral gray, red HIGH badge, urgency banner only when persisted, BP finding in sky accent, grouped factor rows label+code, violet interaction accents, low-priority version metadata); Referral Follow-through card (Pending: Mark Completed / Record Refusal / Cancel Referral; closed states: no buttons). Refusal modal rewritten in Tailwind with `openRefuseModal/closeRefuseModal`, `@if(status === 'Pending')` guard, no legal text; exact refused wording preserved.
- `create.blade.php` — two-column layout for linked referrals (left: Referral Form incl. Facility/Doctor/Reason/Notes/date + hidden ids; right: read-only "Assessment Being Referred / Linked Assessment Evidence (read-only)" panel). Manual referrals are a single centered `max-w-2xl` card with a small neutral "Manual Referral" pill and no evidence column.
- `print.blade.php` — conservative polish only: `@page` size/margin, `break-inside: avoid` on content + signature sections, `no-print` wrapper fixed, `print-container` becomes fluid on print. No dashboard colors, snapshot/evidence rendering unchanged.
- `patients/show.blade.php` — referral quick card renamed "Referral Follow-through", now shows latest referral status badge + destination + date + source chip + "View Referral" button instead of an inline swipe; does NOT duplicate full snapshot evidence (the assessment evidence remains in the risk panel). "Pending Referral Exists" / "Create Referral from this Assessment" block unchanged.
- `risk/monitoring.blade.php` — the "Pending Referral" chip on mobile and desktop is now a quiet outline chip (`border-orange-200`), visually secondary to HIGH/urgent clinical badges; text assertion unchanged.

### Tests

- `Phase16EUiTest` — updated the index assertion that previously expected per-row "Mark Completed" (now expected "View Referral" + no per-row mutation buttons), added presentation tests: A2 (index row does not expose per-row mutations), D3 (linked create page renders "Assessment Being Referred" + "Assessment evidence (read-only)"), I2 (patient profile does not duplicate the full snapshot evidence).
- `ReferralFollowThroughTest` — "renders the corrected refusal wording without a false close claim" moved from the index to the detail page (the index no longer renders the full refusal narrative by design).
- Focused suites (Phase16EUiTest, AssessmentLinkedReferralTest, ReferralFollowThroughTest, RiskMonitoringStatusTest, ReferralAnalyticsTest, PatientProfileRiskPanelTest, Sprint15InteractionUiTest, LegacyPatientShowRenderingTest, MedicalHistoryScopeTest, DeliveredPatientWorkflowTest, PatientExportConsistencyTest, StaffAccessControlTest, AssessmentMetadataPersistenceTest, ReferralIntegrationSchemaTest, ReferralMigrationRollbackSafetyTest) all PASS.
- Full suite: 543 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 200; ProfileTest soft-delete mismatch). Same baseline as prior phases.
- `view:clear` + `view:cache` clean; `php -l` clean on all changed PHP; `git diff --check` exit 0.

### Defense notes

- The visual priority requires the index to communicate status at a glance, so the per-row mutation buttons are removed and all follow-through decisions are made on the detail page, preserving the backend-authoritative safety boundary from 16D.
- Three separately-coded status cards keep Pregnancy (sky), Referral (amber/gray), and Clinical Risk (red) conceptually separate, matching the reviewer's "ONGOING must not look red" and "Pending not equal to Overdue/HIGH".
- The create page's right column is deliberately read-only — same data that will freeze into the snapshot — so the user cannot accidentally change the evidence while writing the referral.
- Print stays a clinical letter (clinic header, patient/pregnancy/ref test info, referral reason/notes, status/refusal/completion, signature) — only print CSS hardened for page-breaks.

The sprint does not change clinical thresholds, does not rewrite working logic, and adds no new active rule.

PHASE 16E UI/UX REVIEW CORRECTION COMPLETE — AWAITING FINAL SPRINT 16 ACCEPTANCE

## Sprint 16 - Phase 16F - Final Acceptance Gate

Status: Complete (verification-first; no feature work, no redesign, no migration, no Sprint 17)

Branch: feature/sprint-16-referral-follow-through (uncommitted)

### Scope & constraint confirmation

- ACCEPTANCE GATE ONLY. No production code changed: no referral logic, status transitions, eligibility, snapshot content, analytics, diagnosis/treatment claims, or DB schema touched. No new fields/statuses. No migrations run.
- `migrate:status` confirms the 16B/16F referral-integration migration remains `[21] Ran`; `git diff --check` exit 0; `php -l` clean; `view:clear` + `view:cache` clean.
- Existing Sprint 16 suites (ReferralIntegrationSchemaTest, ReferralMigrationRollbackSafetyTest, AssessmentLinkedReferralTest, ReferralFollowThroughTest, RiskMonitoringStatusTest, Phase16EUiTest, ReferralAnalyticsTest, StaffAccessControlTest, DeliveredPatientWorkflowTest, LegacyPatientShowRenderingTest, PatientExportConsistencyTest, AssessmentMetadataPersistenceTest, PatientProfileRiskPanelTest, Sprint15InteractionUiTest, MedicalHistoryScopeTest) all PASS.

### What changed (tests + docs only)

- Added `tests/Feature/Phase16FFinalAcceptanceTest.php` closing the genuine acceptance-matrix gaps not already asserted by the existing suites:
  - G: completing, refusing, and cancelling a referral all keep `patient.status = ONGOING` (pregnancy lifecycle stays decoupled through every terminal transition, driven over the real HTTP routes).
  - G9/L4: a legacy `patient.status = REFERRED` is never rewritten and no backfill occurs when a new referral is created and closed against it.
  - J1: referral read routes stay open to admin/staff while every mutation route (store/complete/refuse/cancel) rejects a non-staff user and mutates nothing on the denied attempts.
  - K: index status filter and search behave at the HTTP layer (Pending/Refused filters and name search).
  - K3: index pagination renders 15 rows on page one and the remainder on page two.
  - D6/E7: completed and refused detail pages render no mutation controls (previously only Cancelled was explicitly asserted).

### Tests

- Focused suite `Phase16FFinalAcceptanceTest`: 6 passed (43 assertions).
- Full suite: 549 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 200; ProfileTest soft-delete mismatch) — the same two known failures as every prior phase, plus the six new acceptance tests added by this phase.

### Defense notes

- No assertion was added where the existing suite already proves the behavior (e.g., A8 duplicate-pending is covered by AssessmentLinkedReferralTest + ReferralFollowThroughTest; M1/M4/M5/M6 by ReferralMigrationRollbackSafetyTest), keeping the acceptance suite lean and non-duplicative.
- HTTP-layer assertions were preferred over direct service calls for the transition/decoupling proof so the middleware and route contract are exercised end-to-end.
- Count-based pagination assertions use the fixed `View Referral` link text (twice per row: mobile card + desktop table) because referral-ID hrefs collide on single/double-digit prefixes.

The sprint does not change clinical thresholds, does not rewrite working logic, and adds no new active rule.

PHASE 16F COMPLETE — SPRINT 16 READY FOR HUMAN ACCEPTANCE

## Sprint 17 - Phase 17A — Current-system audit & architecture

Status: Complete (read-only audit; architecture correction approved in human review)

Branch: feature/sprint-17-pregnancy-outcomes (uncommitted)

### Scope & constraint confirmation

- READ-ONLY audit. No files modified, no migration created, no data touched, no commit.
- Verified the current suite reproduces the known baseline: `php artisan test` → 549 passed, 2 pre-existing unrelated failures (ExampleTest guest 302 vs 202; ProfileTest soft-delete mismatch).

### Findings recorded

- `patients.status` is a string column (default `ONGOING`; migration `2026_03_31_174932`) using `ONGOING` / `DELIVERED` / legacy `REFERRED`; DELIVERED currently doubles as pregnancy closure.
- `markDelivered()` (`PatientController`) requires ≥1 Baby, sets `status = DELIVERED` + `delivery_date`, and increments `para`.
- Start New Pregnancy requires `status = DELIVERED`, clones identity into a new row, `gravida +1`, `para` unchanged.
- `delivery_type` is a phantom, non-persisted UI field (Blade fallback `?? 'Normal Delivery'` only).
- EDD never determines outcome; referral lifecycle is independent; legacy `REFERRED` never rewritten (Sprint 16 contract).

### Approved architecture direction (17A correction)

- One-per-pregnancy `pregnancy_outcomes` record (UNIQUE `patient_id` on `patients` rows).
- `patients.status` unchanged: OUTCOME / DELIVERY / REFERRED; no CLOSED; no outcome/follow-up encoding.
- Outcome confirmation via `outcome_type != null` + provenance (no redundant boolean, no duplicated delivery_date).
- Persisted follow-up observations only: STILL_PREGNANT_CONFIRMED, UNABLE_TO_CONTACT. CONFIRMATION_REQUIRED and RESOLVED are derived.
- Outcome vocabulary: DELIVERED only; delivery context: THIS_CLINIC / ANOTHER_FACILITY / HOME / OTHER; minus clinically sensitive categories (deferred).
- No backfill; legacy DELIVERED/ONGOING/REFERRED rows remain valid.

## Sprint 17 - Phase 17B — Pregnancy outcome/follow-up data foundation

Status: Complete (schema/model/vocabulary/tests/docs only; no workflow, no UI, no EDD logic)

Branch: feature/sprint-17-pregnancy-outcomes (uncommitted)

### Scope & constraint confirmation

- DATA FOUNDATION ONLY. No recording routes/controller, no EDD logic, no markDelivered/Baby/para/Start-New-Pregnancy change, no delivered-guard refactor, no sidebar/UI rename, no analytics.
- Additive migration created but NOT executed against the developer DB (awaits explicit authorization).
- No commit, no push.

### What changed

- `database/migrations/2026_08_09_000002_create_pregnancy_outcomes_table.php` (new, additive): one record per pregnancy.
  - `patient_id` UNIQUE FK → patients (CASCADE on hard delete; soft deletes never touch the FK).
  - `outcome_type`, `delivery_location`, `follow_up_status` (nullable strings); `follow_up_recorded_at` / `confirmed_at` (nullable timestamps); `follow_up_recorded_by` / `confirmed_by` (nullable FK users, `nullOnDelete`); `confirmation_source` (nullable string); `notes` (nullable text); timestamps.
  - Deliberately absent: `outcome_confirmed`, `delivery_date` (patients.delivery_date stays canonical).
- `app/Models/PregnancyOutcome.php` (new): fillable, `datetime` casts, `patient()` / `followUpRecordedBy()` / `confirmedBy()` relations, `hasConfirmedOutcome()` = `outcome_type !== null`.
- `app/Models/Patient.php`: added `pregnancyOutcome(): HasOne` relation. No boot/delete-cascade changes (17C).
- `app/Support/PregnancyOutcomeVocabulary.php` (new): outcome/delivery/follow-up/source vocabularies + derived markers; null = "no evidence".

### Vocabulary contract (17B)

- outcome_type: `DELIVERED`.
- delivery_location: `THIS_CLINIC`, `ANOTHER_FACILITY`, `HOME`, `OTHER`.
- follow_up_status (persisted): `STILL_PREGNANT_CONFIRMED`, `UNABLE_TO_CONTACT`. Derived (never persisted): `CONFIRMATION_REQUIRED`, `RESOLVED`.
- confirmation_source: `CLINIC_RECORD`, `PATIENT_REPORT`, `OTHER_FACILITY_REPORT`, `OTHER`.

### Tests

- `tests/Feature/PregnancyOutcomeMigrationTest.php` (6): table/columns; no redundant boolean / duplicate date; UNIQUE patient_id; nullOnDelete provenance; patient cascade; add-drop-add rollback via the real down()/up().
- `tests/Feature/PregnancyOutcomeModelTest.php` (10): relations; casts; all-null unrecorded state; STILL_PREGNANT_CONFIRMED + actor; UNABLE_TO_CONTACT + actor; confirmed DELIVERED struct; mass-assignment of `delivery_date` rejected; legacy DELIVERED/ONGOING/REFERRED without a record remain valid.
- `tests/Unit\Support/PregnancyOutcomeVocabularyTest.php` (7): accepted/rejected vocabularies, derived-vs-persisted boundary, null-unrecorded acceptance.

### Regression

- Focused new: 23 passed (95 assertions).
- Full suite: 572 passed, 2 pre-existing unrelated failures (same two known baselines). Sprint 16 reference/referral suites untouched and green.

### Defense notes

- The UNIQUE `patient_id` on `patients` rows encodes one-per-pregnancy without new composite keys; each `patients` row is one pregnancy episode (Start New Pregnancy clones).
- The boolean `outcome_confirmed` is avoided so `true + outcome_type=null` is structurally impossible; provenance columns persist every recorded fact.
- `patients.delivery_date` remains canonical; the new table owns context/provenance only, avoiding two-date drift.
- User FKs use `nullOnDelete` (provenance survives account removal); `patient_id` cascades match the existing child-record delete semantics.
- No backfill: legacy rows without an outcome record are valid historical records; no evidence was invented.

PHASE 17B COMPLETE — AWAITING SCHEMA/MIGRATION REVIEW BEFORE OUTCOME RECORDING WORKFLOW

## Sprint 17 - Phase 17B — Human schema review correction

Status: Complete (architecture approved; review corrections applied; migration still PENDING)

Branch: feature/sprint-17-pregnancy-outcomes (uncommitted)

### Corrections applied

1. **`hasConfirmedOutcome()` contract.** Previously `outcome_type !== null`. Now requires the full confirmation provenance: `outcome_type` AND `confirmed_at` AND `confirmed_by` AND `confirmation_source`. `delivery_location` is outcome context and is deliberately NOT required. Added tests A–F (outcome_type only / +confirmed_at only / missing confirmed_by / missing confirmation_source / full provenance / null state).
2. **Rollback guard.** `down()` now: table absent → no-op; table present+empty → allow drop; table present with ANY row → throw `RuntimeException` BEFORE any destructive action (no deletion, truncation, or conversion; table stays intact). Added migration rollback tests A–E (empty-down succeeds; populated-down throws; row unchanged after rejected rollback; columns still present; empty-down then up() recreates).
3. **Fixture cleanup.** The user-FK `nullOnDelete` test no longer builds one impossible row with `outcome_type = DELIVERED` + `follow_up_status = STILL_PREGNANT_CONFIRMED`; it uses two separate rows (confirmation-provenance record and follow-up-provenance record) on separate patients.
4. Scope unchanged: 17B data foundation only; no routes/services/EDD/UI/Baby/para/Start-New-Pregnancy/guard/analytics changes.

### Regression

- Focused new/corrected: 34 passed (123 assertions).
- Full suite: 572 passed, 2 pre-existing unrelated failures (same two known baselines). The `down()` guard means any existing row blocks migration rollback — matching the Sprint 16 `Refused`-protection precedent.

PHASE 17B HUMAN SCHEMA REVIEW CORRECTION COMPLETE — AWAITING MIGRATION EXECUTION APPROVAL

## Sprint 17 - Phase 17B — Final confirmation-provenance correction

Status: Complete (review correction applied; migration still PENDING)

### What changed

- **`hasConfirmedOutcome()` no longer requires `confirmed_by`.**
  A historically confirmed outcome now requires `outcome_type != null` + `confirmed_at != null` + `confirmation_source != null`. `confirmed_by` remains optional provenance because the FK is nullable with `nullOnDelete()`: deleting a staff account clears only the live FK reference and must never retroactively flip a historically confirmed DELIVERED outcome back to false.
- Migration **FK policy unchanged** (`confirmed_by` stays nullable + `nullOnDelete`); only its docblock text was clarified.
- Model/class PHPDoc documents the invariant: account removal un-links the actor, never the fact.
- Confirmation-contract tests rebuilt to the definitive set:
  A outcome_type only → false; B +confirmed_at/no source → false; C +source without confirmed_by → true; D full provenance → true; E remains true historically **after** the confirming user is force-deleted and `confirmed_by` becomes null; F missing confirmation_source → false; G null/unrecorded → false.
- All prior rollback-safety corrections retained (populated-table rollback throws; no rows or columns are lost on rejection; empty rollback re-up()).

### Regression

- Focused: 35 passed (130 assertions).
- Full suite: 584 passed, 2 pre-existing unrelated failures (same two known baselines).
- Migration `2026_08_09_000002_create_pregnancy_outcomes_table` remains **PENDING**; no commit, no push.

PHASE 17B FINAL CONFIRMATION-PROVENANCE CORRECTION COMPLETE — AWAITING MIGRATION EXECUTION APPROVAL

---

## Sprint 17 - Phase 17C - Confirmed Delivery Recording Workflow

### Scope
- Backend + workflow only. The existing Patient profile -> Mark as Delivered action now records a confirmed DELIVERED pregnancy outcome transactionally through a dedicated service.
- 17B data foundation integrated. No new migration, no EDD/outcome-monitoring UI, no sidebar/module rename, no new outcome types, no clinical risk/BP/ML/interaction/referral changes.

### Files
- NEW app/Services/PregnancyOutcomeRecordingService.php - authoritative owner of the confirmed-delivery write transaction.
- app/Http/Controllers/PatientController.php - markDelivered() delegates multi-model writes to the service; constructor DI; updateBaby() server-side lifecycle guard (DELIVERED / legacy REFERRED reject 403).
- resources/views/patients/show.blade.php - delivery modal gains Delivery Location + Confirmation Source selects and Outcome/Confirmation Notes; removed dead deliveryForm/delivery_type CS JS (17A findings).
- app/Models/PregnancyOutcome.php - corrected stale class PHPDoc: confirmed_by is optional provenance (nullOnDelete), hasConfirmedOutcome() invariant unchanged.
- NEW tests/Feature/PregnancyOutcomeRecordingTest.php (26 tests).
- docs/IMPLEMENTATION_PROGRESS.md - this section.

### Contract
- One DB::transaction. Patient re-queried with lockForUpdate(); status must be exactly ONGOING (DELIVERED and legacy REFERRED rejected, never rewritten).
- PregnancyOutcome row: outcome_type=DELIVERED, delivery_location + confirmation_source validated server-side via PregnancyOutcomeVocabulary, confirmed_at = server now(), confirmed_by = authenticated staff, notes trimmed or null. Follow-up fields cleared on the confirmed outcome; derived RESOLVED never stored. Exactly one outcome row (patient_id UNIQUE); a blank/unconfirmed placeholder row is updated in place; an already-confirmed outcome rejects the operation.
- Patient: status=DELIVERED, canonical delivery_date, para+1 exactly once. Baby rows: at least one required; all supported fields preserved; each baby date_of_birth must equal the submitted delivery_date (no future DOB).
- Client can never supply outcome_type / confirmed_at / confirmed_by / status / para / follow-up provenance.
- Audit log written only after transaction success, clinically neutral (no admission/verification/referral claims).
- No automatic inference from EDD, visits, referral state, babies, risk, or existing delivery_date. No backfill; legacy DELIVERED without outcome stays valid; legacy REFERRED untouched.

### Tests / results
- Focused: 26 passed (116 assertions).
- Related regression (17B migration/model/vocab, delivered workflow grouping/history/baby-info/print, referral follow-through + Phase 16F acceptance + rollback safety, risk monitoring, staff access): 79 passed (320 assertions).
- Full suite: 610 passed, 2 pre-existing unrelated failures (ExampleTest guest expectation, ProfileTest soft-delete expectation) - baseline was 584 passed / 2 failed; the two failures are unchanged.

### Static / state
- php -l clean on all created/modified PHP; PHPUnit/view cached; git diff --check clean.
- Migration 2026_08_09_000002_create_pregnancy_outcomes_table remains Ran [22]; no new migration created.

### Deliberately NOT done (Phase 17D scope)
- Pregnancy Outcome Monitoring page/dashboard, EDD follow-up queue, CONFIRMATION_REQUIRED / RESOLVED UI, Still-Pregnant / Unable-to-contact workflows, sidebar/model rename, delivery_type column, mode-of-delivery classification, sensitive outcomes, gestational-age-at-birth inference, referral-to-outcome linkage.

PHASE 17C COMPLETE - AWAITING HUMAN REVIEW BEFORE PREGNANCY OUTCOME MONITORING / EDD FOLLOW-UP

### Phase 17C final hardening (authoritative service boundary)

Per human review, the core delivery-date/baby-DOB invariants are now enforced by PregnancyOutcomeRecordingService itself, independent of controller validation:

- assertDateIntegrity() rejects, for direct service callers AND the HTTP path:
  A invalid/unparseable delivery date; B future delivery date; C baby DOB not on the same calendar date as the delivery date; D future baby DOB; E malformed baby DOB; F missing baby date/time (with assertBabyContract); G empty baby array.
- Comparison is normalized through Carbon::parse() + isSameDay()/isFuture() on calendar dates, never raw string equality. delivery_date is never inferred from babies and vice versa; both must be supplied.
- PatientController::markDelivered() validation (delivery_date required/date/before_or_equal:today; babies.*.date_of_birth required/date/before_or_equal:today/same:delivery_date; babies.*.time_of_birth required/date_format:H:i) remains in place � defense in depth.
- Direct-service tests added (service H, A/G, B/G, C/G, D/G, E/G, F/G): every rejection leaves patient.status ONGOING, delivery_date null, para unchanged, zero babies, no confirmed outcome.
- Results: focused 33 passed (162 assertions); related 17B/17C regression 75 passed (296 assertions); full suite 617 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete). Static checks clean; migration 2026_08_09_000002 remains Ran [22]; no migration/schema/route/UI/status changes.

PHASE 17C FINAL HARDENING COMPLETE - AWAITING HUMAN ACCEPTANCE BEFORE PHASE 17D

---

## Sprint 17 - Phase 17D - Pregnancy Outcome Monitoring + EDD Follow-up

### Scope
- Read-only monitoring module: a derived-state queue (RESOLVED / STILL_PREGNANT_CONFIRMED / UNABLE_TO_CONTACT / CONFIRMATION_REQUIRED / NOT_YET_DUE / LEGACY_DELIVERED / LEGACY_REFERRED / INVARIANT_VIOLATION) plus staff-only follow-up writes. Never infers DELIVERED from a passed EDD; delivery remains exclusively the 17C confirmed workflow.
- Sidebar renamed to "Pregnancy Outcome Monitoring"; Patient profile gains a compact monitoring card.

### Files
- NEW app/Services/PregnancyOutcomeMonitoringService.php - read/derivation only. Deterministic deriveState(asOf), 7-day follow-up window (inclusive both ends), STATE_LABELS, STATE_FILTERS (friendly slugs), isFollowUpEligible, daysUntilOrPastEdd (asOf-first signed diff), countByState.
- NEW app/Services/PregnancyOutcomeFollowUpService.php - write path. DB::transaction + lockForUpdate, server-stamped follow_up_recorded_at/by, create-or-reuse single outcome row, never touches patient status/notes/delivery_date/para/referrals/clinical data.
- app/Support/PregnancyOutcomeVocabulary.php - added DELIVERY_LOCATION_LABELS, CONFIRMATION_SOURCE_LABELS, FOLLOW_UP_STATUS_LABELS + label helpers.
- NEW app/Http/Controllers/PregnancyOutcomeController.php - constructor DI of both services; index() (population, derived rows, stats, search, state-slug filter, LengthAwarePaginator 15) + recordStillPregnant()/recordUnableToContact() (staff-only, DomainException -> error bag, audit log).
- routes/web.php - GET /pregnancy-outcomes (auth) name pregnancy-outcomes.index; staff-group POSTs .../still-pregnant and .../unable-to-contact.
- resources/views/pregnancy-outcomes/index.blade.php - 4 summary cards, search, friendly state-filter chips, table with provenance labels for confirmed deliveries, follow-up actions only on eligible ONGOING rows, pagination.
- resources/views/layouts/app.blade.php - sidebar item renamed to Pregnancy Outcome Monitoring.
- app/Http/Controllers/PatientController.php + resources/views/patients/show.blade.php - eager-load pregnancyOutcome, pass derived monitoring state + days-until/past EDD, compact card for ONGOING/DELIVERED.
- NEW tests/Unit/Services/PregnancyOutcomeMonitoringServiceTest.php (18), tests/Feature/PregnancyOutcomeFollowUpTest.php (16), tests/Feature/PregnancyOutcomeMonitoringUiTest.php (21).

### Contract
- Derived states never persisted; patient.status stays the single lifecycle truth. INVARIANT_VIOLATION surfaces ONGOING + confirmed-outcome inconsistencies for manual review, never auto-corrected.
- Follow-up recorded between asOf-7d and asOf (inclusive) is fresh; older observations expire back to CONFIRMATION_REQUIRED so stale "still pregnant" never suppresses outcome confirmation.
- Writes reject DELIVERED, legacy REFERRED, and confirmed outcomes; admin 403 on all mutation routes; client-supplied actor/timestamp ignored.
- Raw enum strings never appear in DOM/URLs (slug filters + label maps); raw-enum absence is test-asserted.

### Tests / results
- Focused: 54 passed (unit 18 + feature follow-up 16 + feature UI 20 = 132 assertions).
- Full suite: 671 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - baseline was 617 passed / 2 failed; the two failures are unchanged, +54 passing new tests.

### Static / state
- php -l clean on all created/modified PHP; git diff --check clean.
- Migration 2026_08_09_000002_create_pregnancy_outcomes_table remains Ran [22]; no new migration created.

### Deliberately NOT done
- EDD-reassignment/re-dating, mode-of-delivery classification, sensitive outcomes, gestational-age-at-birth inference, referral-to-outcome linkage, dashboard analytics charts, delivery_type column.

PHASE 17D COMPLETE - AWAITING HUMAN ACCEPTANCE

---

## Sprint 17 - Phase 17D Human-Review Correction - Follow-up Eligibility Boundary

### Scope (only this correction)
Outcome follow-up observations (STILL_PREGNANT_CONFIRMED / UNABLE_TO_CONTACT) were previously accepted for any ONGOING pregnancy without a confirmed outcome. Review found staff could record follow-ups before outcome monitoring was due. Corrected the authoritative boundary so follow-up requires the EDD to have already passed (today > EDD).

### Files changed
- app/Services/PregnancyOutcomeMonitoringService.php - added isEddPassed (strict today > EDD; null=false; today=false); isFollowUpEligible now requires status ONGOING + no confirmed outcome + edd not null + isEddPassed. Accepts optional asOf for determinism.
- app/Services/PregnancyOutcomeFollowUpService.php - constructor-DI of PregnancyOutcomeMonitoringService; enforces isFollowUpEligible after status and confirmed-outcome guards, so backend authority always matches presentation. Delivered / legacy REFERRED / confirmed-outcome messages unchanged.
- resources/views/pregnancy-outcomes/index.blade.php - Unable to Contact summary wording: "Reached near EDD but not contacted recently" -> "Recent follow-up attempt was unsuccessful." No other redesign.
- tests/Unit/Services/PregnancyOutcomeMonitoringServiceTest.php - R reworded + new R2/R3/R4/R5 (future/today/null/passed EDD eligibility).
- tests/Feature/PregnancyOutcomeFollowUpTest.php - new AE/AF/AG (direct service invocation throws for future/today/null EDD) and AH/AI/AJ (direct POST writes nothing).
- tests/Feature/PregnancyOutcomeMonitoringUiTest.php - new AV/AW/AX (no follow-up buttons future/today/null EDD), AY (buttons on passed EDD), AZ (wording). AU preserved.

### Results
- Focused (monitoring unit + follow-up + monitoring UI): 69 passed (175 assertions).
- Related regression (17B/17C migration/model/vocab/recording + Sprint 16 referral/Phase16E/16F/delivered/risk-monitoring/staff-access): 135 passed (613 assertions).
- Full suite: 686 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - baseline 617+54 was /2; failures unchanged.
- php -l clean on all touched PHP; view:clear + view:cache clean; git diff --check clean.
- Migration 2026_08_09_000002_create_pregnancy_outcomes_table remains Ran [22]; no migration/schema/status/outcome-type/referral/risk/BP/ML/interaction/Start-New-Pregnancy changes.

PHASE 17D HUMAN REVIEW CORRECTION COMPLETE - AWAITING FINAL ACCEPTANCE BEFORE 17E

---

## Sprint 17 - Phase 17E - Patient Profile Follow-up Modal + Escaping/Blade Integrity

### Scope (incremental, per design rules)
Follow-up actions on the patient profile were previously triggered by direct POST forms. This phase:
- Replaces direct POST forms on the patient profile with safe modal-trigger buttons (staff only, ONGOING + passed EDD).
- Reuses the shared outcome-confirm-modal component on `patients/show`, gated so modal markup (and its JS string references to `data-outcome-confirm-trigger`) is only emitted when follow-up controls actually render.
- Fixes a Blade compiler regression that silently corrupted compiled output when inline `@php(...)` was used.

### Files changed
- resources/views/patients/show.blade.php - fixed the inline `@php($outcome = $patient->pregnancyOutcome)` line, which was swallowed by Blade's `storePhpBlocks()` regex and compiled into a raw region causing "unexpected endforeach" parse errors; converted to a proper `@php ... @endphp` block. Wrapped `<x-outcome-confirm-modal />` in the same gate as the trigger buttons (ONGOING + $monitoringEligible + non-admin) so future/EDD-today/null-EDD/DELIVERED pages contain no trigger string.
- tests/Feature/PregnancyOutcomePhase17ETest.php - use `assertSeeHtml` (escape=false) for raw-attribute assertions (`data-outcome-action="..."`, `id="outcomeConfirmModal"`, `name="_token"`, etc.) that were incorrectly being escaped to `&quot;`.

### Results
- Focused FE 'PregnancyOutcomePhase17ETest': 18 passed (79 assertions).
- php -l clean on touched PHP; view:clear + view:cache clean; debug scan/compile artifacts removed.
- No migration/schema/clinical-threshold/service-behavior changes.

PHASE 17E COMPLETE - AWAITING HUMAN ACCEPTANCE

---

## Sprint 17 - Phase 17E Continuation - Follow-up UI/UX Finalization

### Scope (incremental, per design rules)
Polish the staff-facing Pregnancy Outcome follow-up workflow without changing any backend authority. The confirmation modal is the single shared mechanism for both "Confirm Still Pregnant" and "Unable to Contact"; clicking a trigger only opens the modal, and only the in-modal Confirm button submits the POST.

### Files changed (this 17E continuation)
- resources/views/components/outcome-confirm-modal.blade.php - improved reusable modal: action-tone accent (green confirm vs rose alert icon via `data-outcome-tone`), patient name line, spinner + disabled "Saving..." busy state, `aria-labelledby`/`aria-describedby` wiring, `role="dialog"` + `aria-modal`, focus moved into the dialog on open, focus restored to the triggering button on close, Tab focus trap, Escape closes without submitting, backdrop click closes only an open modal, clicks inside the panel never close it, and a single document-level delegated listener (no duplicate listeners across re-renders).
- resources/views/patients/show.blade.php - trigger buttons now carry `data-outcome-tone`, inline icons, `shadow-sm`, and `focus:ring` visible focus states; monitoring-state badge uses per-state colors.
- resources/views/pregnancy-outcomes/index.blade.php - trigger buttons (desktop + mobile) upgraded with `data-outcome-tone`, icons, focus rings; per-state badge colors; modal emission gated to staff pages that actually have an observable row (`$hasFollowUpRows`).
- app/Http/Controllers/PregnancyOutcomeController.php - row builder now computes `state_badge_class` (kept out of Blade so no inline `@php`/`@endphp` block collides with the existing inline `@php($patient = ...)` loop pattern that Blade's `storePhpBlocks()` regex can corrupt).
- tests/Feature/PregnancyOutcomeMonitoringUiTest.php - AK rewritten to assert per-patient follow-up route URLs (still-pregnant/unable-to-contact present only for the eligible ONGOING row, absent for the DELIVERED row) instead of a fragile global `substr_count` of a label string that legitimately appears in both desktop and mobile views.

### UI/UX behavior improved
- Staff click "Confirm Still Pregnant" / "Unable to Contact" -> modal opens, nothing is POSTed. Only in-modal Confirm submits.
- Modal shows patient name, the exact action label, a concise explanation, Cancel and Confirm buttons.
- Confirm enters a visible disabled "Saving..." state with spinner on submission; double-submit is impossible (button disabled + form submit guard).
- Cancel, Escape, and backdrop click all close the modal without submitting; focus returns to the trigger.
- Full keyboard operation via Tab focus trap; modal is announced via aria-labelledby/aria-describedby.
- Action buttons are distinguishable (green = still pregnant, rose = unable to contact) with visible focus states.
- Monitoring page shows state-appropriate badge colors and keeps follow-up controls only on eligible ONGOING rows (both desktop table and mobile cards).
- Patient profile shows follow-up controls only when actually eligible (ONGOING + passed EDD + non-admin); DELIVERED / future / today / null EDD pages contain no trigger markup and no modal.

### Tests / results
- Focused 17E + monitoring UI + follow-up: 65 passed (214 assertions).
- Outcome unit/feature (monitoring service, vocabulary, recording, model, migration): 90 passed (334 assertions).
- Sprint 16/17 regressions (referral follow-through, linked assessment, recording, delivered workflow, staff access, risk monitoring): 96 passed (380 assertions).
- Phase 16 + referral + outcome migration/model: 92 passed (426 assertions).
- Full suite: 704 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - unchanged from baseline.
- php -l clean on touched PHP; view:clear + view:cache clean; git diff --check clean.
- No migration/schema/clinical-threshold/service-behavior/vocabulary changes.

PHASE 17E UI/UX FINALIZATION COMPLETE - AWAITING HUMAN ACCEPTANCE

## Phase 1 — UI/UX Visual Foundation (Design Tokens + Shared Components)

Status: Complete

Scope (incremental, per design rules): Phase 1 is visual-system work only. Define one shared design-token source of truth and standardize the existing reusable Blade components so every later page restyle draws from a single foundation. No controllers, routes, database, ML, risk, referral, export, or permission logic was touched. No major page was redesigned.

### Design tokens (single source of truth)
- `resources/css/app.css` - added `@layer base` `:root` tokens mirroring the app-shell variables in `layouts/app.blade.php`: semantic colors (`--color-primary`, `--color-primary-hover`, `--color-success`, `--color-warning`, `--color-danger`, `--color-info`, `--color-neutral` plus soft variants, page/card background, borders, muted text), radius (`--radius-card` 16px, `--radius-btn` 10px, `--radius-input` 8px, `--radius-badge` 9999px, `--radius-modal` 16px), one subtle card shadow (`--shadow-card`) plus a single popover shadow, spacing scale (`--space-page/card/section/form/modal`, `--cell-pad`), and typography (`--font-sans` DM Sans-first, `--font-serif` Lora).
- `@layer components` in `app.css` - foundational classes used by the shared components: `.card`, `.btn` base + `.btn-primary/.btn-secondary/.btn-danger/.btn-link`, `.status-badge` + five variants (`.status-badge-success/warning/danger/info/neutral`), `.alert` + four variants (`.alert-success/error/warning/info`). Badge classes are named `status-badge` to avoid colliding with the existing `.badge` utility already used by the topbar notification dot and dashboard KPI badges.
- `tailwind.config.js` - extended theme: `fontFamily.sans` now DM Sans-first, new `font-display` (Lora) for titles, and semantic `colors.{primary,success,warning,danger,info,neutral}` with `DEFAULT` + `hover` + `soft` shades so pages/utilities like `bg-primary`, `focus:ring-primary/30`, `bg-primary-soft` are available without new color families.

### Shared components standardized
- `resources/views/components/primary-button.blade.php` - now `.btn .btn-primary` (blue, white text, 40px height, 10px radius, normal-case). Previously Breeze `bg-gray-800` + uppercase/tracking-widest.
- `resources/views/components/secondary-button.blade.php` - now `.btn .btn-secondary` (white, border, normal-case).
- `resources/views/components/danger-button.blade.php` - now `.btn .btn-danger` (red, normal-case).
- `resources/views/components/text-input.blade.php` - unified input appearance: `h-10 px-3 text-sm rounded-lg border-gray-300 focus:border-primary focus:ring-2 focus:ring-primary/30`. Indigo focus and `rounded-md` removed; dark-mode-only classes dropped (app is light-only).
- `resources/views/components/input-label.blade.php` - `text-slate-700` label (was gray-700).
- `resources/views/components/modal.blade.php` - overlay `bg-slate-900/60` (was gray-500/75), panel `rounded-2xl` + `shadow-lg` (was `rounded-lg` + `shadow-xl`). All Alpine/trap-focus/escape behavior untouched.
- `resources/views/components/input-error.blade.php` - unchanged (already matched tokens: red-600 text-sm).

### New shared components
- `resources/views/components/status-badge.blade.php` - `x-status-badge`, `variant` prop (success/warning/danger/info/neutral), renders `.status-badge` pill. One badge language for later phases.
- `resources/views/components/flash.blade.php` - `x-flash`, `type` + optional `message` prop or slot, renders `.alert` variants.
- `resources/views/components/error-summary.blade.php` - `x-error-summary`, renders `$errors->all()` in a `.alert-error` box.
- `resources/views/components/icon-title.blade.php` - `x-icon-title`, soft primary icon tile + title + subtitle header block.
- `resources/views/components/app-header.blade.php` - `x-app-header`, shared page-header structure with title/subtitle and an `$actions` named slot.

These new components are created but intentionally not wired into major pages yet (Phase 3+). They are the adoption target for the audit's repeated page-header/badge/flash patterns.

### Visual differences introduced
- Auth/profile pages that use the shared components now show blue primary buttons, bordered secondary buttons, rounded inputs with a blue focus ring, and a darker modal overlay with `rounded-2xl` panels. No uppercase/tracking-widest anywhere in the shared set.
- Major pages (Patient Profile, dashboards, login, records, visits, risk, referrals, delivered, audit logs) were not restyled; no page-specific markup was edited.

### Tests / results
- `npm run build`: succeeded (vite 7.3.1); compiled CSS confirmed to contain token vars, `.status-badge*`, `.btn*`, `.alert*`, `.card`, and the `focus:border-primary` / `focus:ring-primary/30` utilities.
- `php artisan view:cache`: clean (all Blade incl. 5 new components compile).
- `php artisan test`: 704 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - verified identical on the stashed baseline.
- No migrations, schema, clinical thresholds, service behavior, vocabulary, routes, or permissions changed.

Design defense: tokens live in one place (`app.css` + Tailwind config) so later page restyles reference a single palette instead of per-page hex values; the app-shell variables in `layouts/app.blade.php` are mirrored (not replaced) so the inline shell styles keep working with zero behavioral risk. `status-badge` naming avoids overriding the existing `.badge` notification dot used in the topbar on every authenticated page.

PHASE 1 UI/UX VISUAL FOUNDATION COMPLETE - AWAITING HUMAN ACCEPTANCE

## Phase 2 — Page-Level UI/UX Standardization (Headers, Badges, Flashes, Error Summaries)

Status: Complete

Scope (incremental, per design rules): Phase 2 adopts the Phase 1 shared components (`x-app-header`, `x-icon-title`, `x-status-badge`, `x-flash`, `x-error-summary`) across the module pages listed below, removes decorative "AI-looking" elements (gradient headers, emojis, random indigo/purple/pink accents), and standardizes flash/error handling. Patient Profile arrangement, dashboards, sidebar/IA, backend logic, clinical thresholds, vocabulary, routes, and permissions were NOT changed. All page copy used by tests was kept byte-identical; tests assert text, not badge classes.

### Error summary component enhancement
- `resources/views/components/error-summary.blade.php` - added an optional `title` prop (e.g. "Please fix the following errors:"); content wrapped in `<div class="flex-1">`, list is `list-disc pl-5 space-y-1` with `mt-1` spacing when a title is present. Existing no-title usage (auth/profile) unaffected.

### Modules migrated (view-only)
- `referrals/index`: self-contained `$sourceVariant`/`$statusVariant` mappings (PHP closures do NOT auto-capture parent scope - the first draft threw "Undefined variable $sourceIndigo" and was fixed by inlining the mapping inside the closure), `x-app-header` with analytics actions, `x-flash` success/error, source + status badges to `x-status-badge`.
- `referrals/show`: `$pregnancyVariant`/`$statusVariant`/`$riskVariant` badges, `x-flash`, `x-app-header` (patient name title, destination/doctor/date subtitle, Back + Print `btn` actions), "Referral Details" pill removed.
- `referrals/create`: `x-app-header`, `x-error-summary` with title, Assessment panel indigo→blue, risk badge, Manual Referral badge.
- `patients/delivered`: `x-icon-title` header + monitoring `btn btn-secondary`, `x-flash`/`x-error-summary`, Total Babies/Confirmed/Historical badges, row buttons → `btn btn-secondary`/`btn btn-primary`, modal header `bg-indigo-50`→`bg-gray-50`.
- `pregnancy-outcomes/index`: `x-icon-title`, indigo→blue accents/focus/hover, info box blue, `x-flash`/`x-error-summary`, filter pills active `bg-primary text-white`, state badges wrapped in `x-status-badge` (controller-provided `state_badge_class` passed through so colors win and the pill shape is gained).
- `patients/pregnancy-history`: risk badge → `x-status-badge` (danger/success/warning), Delivered badge → success.
- `audit_logs/index`: 📜 emoji removed, `x-app-header`, filter card `shadow-sm` + focus states, `btn btn-primary`, action/module badges → `x-status-badge` (CREATE→success, UPDATE→info, DELETE→danger, module→neutral).
- `patients/index`: duplicate top flash removed, `x-app-header` (Archived + Add New Patient actions), `x-flash`, auto-hide script retargeted from `.bg-green-100` to `.alert-success`.
- `patients/trashed`: `x-app-header` + `x-flash`.
- `patients/create` + `patients/edit`: gradient headers → `bg-gray-50` card header with `x-icon-title`, `shadow-lg`→`shadow-sm`, errors → `x-error-summary`.
- `prenatal_visits/index`: `x-app-header` (Add Prenatal Visit action), purple stat icon→blue, risk badges → `x-status-badge` (danger/success/warning).
- `prenatal_visits/create` + `edit`: gradient headers → clean `x-icon-title` headers, errors → `x-error-summary`, purple section icon→blue, edit risk badge → `x-status-badge`.
- `ultrasounds/create` + `edit`: gradient purple-pink/amber-yellow headers → clean `x-icon-title` headers, errors → `x-error-summary`, all `focus:ring-purple-500`→blue, pink/yellow icons→blue, purple submit buttons→blue.
- `birth_plans/create`: header → `x-app-header` (Back to Patient Profile action).
- `medical_histories/create`: header → `x-app-header`, session info/error → `x-flash` info/error.
- `staff/index`: header → `x-app-header` in white bar, flash → `x-flash`, `shadow-md`→`shadow-sm`.
- `staff/create` + `staff/edit`: `shadow`→`shadow-sm border-gray-100`, `x-app-header`, standardized inputs, submit → `btn btn-primary`, emoji code comments removed.
- `risk/monitoring`: header normalized to `x-icon-title` (blue soft tile) only; badge system left as-is (dashboards out of redesign scope).

### Emoji cleanup
- User-facing `⚠️` prefixes removed from JS warning strings in `ultrasounds/create`, `prenatal_visits/create`, `prenatal_visits/edit` (`showWarning(...)` + `gaHint.innerHTML`). Not asserted by tests.

### Badge semantic mapping applied
HIGH→danger, LOW→success, ASSESSMENT INCOMPLETE→warning, ONGOING→info, DELIVERED→success; referral Pending→warning, Completed→success, Refused→danger, Cancelled→neutral; Assessment-linked→info, Manual Referral→neutral.

### Tests / results
- `npm run build`: succeeded; compiled CSS confirmed to contain `bg-primary`, `status-badge-*`, `alert-*`, `btn-*` classes.
- `php artisan view:cache`: clean (all Blade compiles).
- `php artisan test`: 704 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - identical to the Phase 1 baseline. A regression (undefined `$sourceIndigo` closure in `referrals/index`) surfaced during the run, was fixed by making the closure self-contained, and the affected suites (`ReferralAnalyticsTest`, `Phase16EUiTest`, `Phase16FFinalAcceptanceTest`) re-ran green.
- `git diff --check`: clean.
- No controllers, routes, migrations, schema, clinical thresholds, service behavior, vocabulary, or permissions changed.

Design defense: `x-status-badge` provides one pill language while keeping semantic coloring (danger=high risk, success=low/delivered, warning=incomplete/pending, info=ongoing/linked); gradient headers were replaced with clean card headers so the blue primary color reads as the single interactive accent; all flash/error surfaces route through the shared alert components so future tone changes happen in one place. Risk Monitoring dashboard badges were intentionally left untouched (dashboards and Patient Profile are out of the current redesign scope).

PHASE 2 PAGE-LEVEL UI/UX STANDARDIZATION COMPLETE - AWAITING HUMAN ACCEPTANCE

## Phase 3 — Patient Profile UI/UX Redesign (resources/views/patients/show.blade.php)

Status: Complete

Scope: View-only redesign of the Patient Profile page. No controllers, routes, migrations, schema, services, clinical thresholds, vocabulary, referral/export/risk logic, permissions, or queries changed. Dashboards and auth pages untouched (out of scope until approved). All test-asserted page copy preserved byte-identical; tests assert text, not markup.

### Shared CSS patterns added (`resources/css/app.css`, `@layer components`)
- `.panel`, `.panel-header`, `.panel-title` (with svg sizing), `.panel-body` - standardized white card with clean gray header bar and blue icon.
- `.kv-row`, `.kv-label`, `.kv-value` - definition-list rows used by the priority strip and Birth Plan.
- `.stat-label`, `.stat-value` - small-stat cells used by the Latest Prenatal Visit strip and Baby Information.
- `.th-cell`, `.td-cell` - table cell spacing replacing repeated `px-4 py-3` utility strings on the profile tables.

### Header + priority strip
- Patient header now a `panel` wrapping `x-app-header` (name title, `age years · contact · address` subtitle, actions slot). Actions standardized: Edit Profile / Complete Records First (disabled w/ tooltip) / View Pregnancy History / Download = `btn btn-secondary`; Add Record / Refer Patient / Start New Pregnancy = `btn btn-primary`; Mark as Delivered = `btn btn-danger`. Status badge row shows Completed Pregnancy / Pending Referral / Assigned Staff.
- New full-width priority strip: "Current Pregnancy" panel (status `x-status-badge`, Gravida/Para/Previous CS/Miscarriage/LMP/EDD `kv-row`s) + "Basic Information" panel (Birthdate/Age/Civil Status/Contact/Address/PhilHealth Member/Number).
- New "Latest Prenatal Visit" panel using `$patient->prenatalVisits->first()` (no new queries) with stat cells (Visit Date, BP, Weight, Temperature, Gestational Age, Fetal Heart Tone) and a "View all visits ->" anchor to `#prenatal-visits-section`.
- Old right-column "Pregnancy Summary" and "Quick Information" cards removed; their data now lives in the top strip.

### Sections (left column, `lg:w-2/3`)
- Baby Information: card per baby with `sex-badge` class added to the static sex pill (fixes duplicate-badge quirk in `updateBabyDisplay` JS), display grid keeps 4 `p` value cells in DOB/time/weight/length order for the JS selectors, edit form focus rings pink->blue. `baby-card`, `data-baby-id`, `.baby-display-mode`, `.baby-edit-mode`, `.baby-edit-form`, `.edit-baby-btn` preserved.
- Prenatal Visits: wrapper now has `id="prenatal-visits-section"` (anchor target), risk cells use `x-status-badge` (danger/success/warning/neutral) with unchanged High/Low/Assessment Incomplete/Unknown labels, `#visit-details-{id}` toggle rows and edit/delete actions preserved.
- Ultrasound Records: `panel` header with `btn btn-primary` Add action; lightbox thumbnails/`us-lightbox-trigger`/`#usLightbox` untouched.
- Medical History: condition grid replaced by chip rows; present = blue pill + "Yes", absent = muted bordered pill. Group titles/notes, the Diabetes/Anemia scope note, and the "condition recorded during a visit" amber box preserved.
- Birth Plan: six fields as `kv-row` definition list; Edit/Add actions standardized.
- Risk Assessment panel: hero replaced by `panel-header` + tinted status band with `x-status-badge` (HIGH->danger "HIGH RISK", LOW->success "LOW RISK", ASSESSMENT INCOMPLETE->warning) and a red "URGENT CLINICAL REVIEW" chip. All inner explainability blocks (B-K, interaction section, linked-referral entry 16C, "NO ASSESSMENT AVAILABLE" fallback) preserved unchanged.

### Referral card + modals
- Referral Follow-through card: `panel` with `x-status-badge` for Pending/Completed/Refused/Cancelled; detail text, Assessment-linked/Manual Referral label, View Referral action preserved.
- Delivery modal: green gradient header -> clean gray header with blue icon, green focus rings -> blue, footer buttons -> `btn btn-secondary`/`btn btn-primary`. `#deliveryModal` id, delivery_date/delivery_location/confirmation_source/outcome_notes and `babies[]` inputs preserved (server fields unchanged).
- Download modals (validation/format/confirm/success) kept as-is (already Phase 2 neutral). Baby edit focus rings pink->blue in both static markup and the `addAnotherBaby()` JS template.

### JS
- All inline JS preserved (visit details toggle, delivery/download modals, lightbox, baby edit/display sync). Only class strings inside JS templates (blue focus rings) were updated. `.flex.items-center.justify-between` baby header kept for sex-badge insertion; `.sex-badge` static pill now carries the class.

### Tests / results
- `npm run build`: succeeded (vite 7.3.1).
- `php artisan view:cache`: clean (all Blade compiles).
- `php artisan test`: 704 passed, 2 pre-existing unrelated failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete) - identical to the Phase 1/Phase 2 baseline. Profile-related suites green: PatientProfileRiskPanelTest, LegacyPatientShowRenderingTest, ExplainabilitySprint7Test, MedicalHistoryScopeTest, PregnancyOutcomePhase17ETest, Phase16EUiTest, Sprint15InteractionUiTest, PatientExportConsistencyTest.
- `git diff --check`: clean.
- No controllers, routes, migrations, schema, clinical thresholds, service behavior, vocabulary, permissions, or queries changed.

Design defense: the profile now follows a clinical hierarchy (identity -> pregnancy status -> attention items -> latest visit -> records -> actions) instead of burying the risk panel above summary cards; the risk panel keeps its full explainability surface but the status reads as a clean badge band; all shared patterns (panel, kv-row, stat-cell, th/td-cell) were added to `app.css` so other pages can adopt them without repeating utility strings. The monitoring outcome panel and REFERRED banner were kept as tinted banners (attention items) rather than neutral cards so clinical flags stay visible. Inline JS was left in the view (not extracted) to keep the change risk-free for the baby-edit and download flows; only CSS classes inside templates changed.

PHASE 3 PATIENT PROFILE UI/UX REDESIGN COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17F — Pregnancy Outcome Monitoring List: Active vs Completed/Historical Split

Status: Complete

Scope: Presentation-layer improvement of the Pregnancy Outcome Monitoring list (Sprint 17D/17E). The monitoring page is split into an "Active Monitoring" queue (CONFIRMATION_REQUIRED, STILL_PREGNANT_CONFIRMED, UNABLE_TO_CONTACT, NOT_YET_DUE) and a "Completed & Historical" section (RESOLVED, LEGACY_DELIVERED, LEGACY_REFERRED, INVARIANT_VIOLATION) collapsed by default; the filter chips now cover every derived state; a legend explains Status vs Monitoring State and the follow-up buttons. No clinical behavior, `deriveState()`, thresholds, vocabulary, routes, permissions, schema, or write flows changed. Derivation is untouched; only presentation of derived state changed.

### Backend
- `app/Services/PregnancyOutcomeMonitoringService.php`:
  - Added `ACTIONABLE_STATES` and `HISTORY_STATES` constants (single source of truth for the two buckets).
  - Extended `STATE_FILTERS` with 4 new slugs: `not-yet-due`, `legacy-delivered`, `legacy-referred`, `needs-review` (INVARIANT_VIOLATION).
  - Added `STATE_FILTER_GROUPS` (slug groups `actionable` / `history`) so the controller and Blade agree on chip grouping.
  - `deriveState()` and every derivation helper left byte-identical.
- `app/Http/Controllers/PregnancyOutcomeController.php` (`index()`):
  - After search/state-filter/sort, `$rows` are split into `$actionable` and `$history` by state membership.
  - `$paginator` keeps its previous semantics (the active queue, 15/page) so the existing pagination contract (`total()`/`perPage()`) is unchanged; a new `$historyPaginator` paginates the historical bucket.
  - `$showActive`/`$showHistory` flags: no chip -> both; an actionable chip -> active only; a history chip -> history only (expanded). `stateFilterSlug` passed to the view so the search form preserves the active chip.

### UI/UX (`resources/views/pregnancy-outcomes/`)
- `index.blade.php`:
  - Chips now render in two labeled groups ("Actionable" with the All reset, "History & Review"), covering all 8 derived states.
  - Body renders two stacked sections: **Active Monitoring** (always visible, own pagination) and **Completed & Historical** (native `<details>` collapsed by default, auto-opened when a history chip is selected, own count + pagination).
  - Info box expanded into a legend: Status (lifecycle) vs Monitoring State (action), follow-up buttons record observations only (never delivery), and the Completed & Historical grouping note.
  - Search form preserves the `state` param via a hidden input so search/chips don't drop context.
- New `_monitoring-rows.blade.php` partial: the desktop table + mobile cards extracted into a single renderer shared by both sections (single source of markup; includes the "Historical — not part of active monitoring" hint on dimmed legacy rows). No row content, labels, actions, or routes changed.

### Tests / results
- `tests/Feature/PregnancyOutcomeMonitoringUiTest.php`: 8 new tests (BB-BH) covering the 4 new chips, default-collapsed history section, history-chip expansion hiding Active Monitoring, and the active/history paginator split. All 50+ pre-existing assertions pass unchanged (the history section stays in the DOM so AG/AH/AI `assertSee` still resolve; AN's `$paginator` contract is preserved).
- `php artisan test`: green aside from the 2 pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete).
- `npm run build`: succeeded (vite 7.3.1).
- `php artisan view:cache`: clean.
- `git diff --check`: clean.
- No clinical logic, derivation, thresholds, vocabulary, routes, permissions, schema, or write flows changed.

Design defense: the page is an action queue, so the actionable states keep the primary, always-visible section, while terminal/diagnostic rows (RESOLVED, LEGACY_*, INVARIANT_VIOLATION) are grouped into a collapsed native `<details>` so the queue stays uncluttered without moving records off-page (history stays reachable and testable in one request). The buckets and chips are driven by service constants (`ACTIONABLE_STATES`/`HISTORY_STATES`/`STATE_FILTER_GROUPS`) so presentation never re-implements derivation. Keeping `$paginator` as the active queue preserves the existing pagination contract and minimizes test churn. Grouping by mother identity was intentionally deferred (Phase D) as a higher-risk change that would alter pagination/search semantics.

PHASE 17F MONITORING LIST ACTIVE/HISTORY SPLIT COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17G — Pregnancy Outcome Monitoring List: UI Simplification (Revert 17F Grouping)

Status: Complete

Scope: UI simplification of the Pregnancy Outcome Monitoring list. The Sprint 17F "Active Monitoring vs Completed & Historical" split was judged too complicated for daily use and was reverted. The page returns to the original single-queue layout: one filter row, one monitoring table, original row actions, and the concise explanation. Only the 17F UI-specific grouping/filter additions were removed; every earlier UI/UX improvement (Phase 1/2 standardization of this page, 17D/17E monitoring feature) and ALL clinical/monitoring logic is preserved byte-for-byte.

### Removed (only the 17F UI additions)
- `app/Services/PregnancyOutcomeMonitoringService.php`: removed `ACTIONABLE_STATES`, `HISTORY_STATES`, `STATE_FILTER_GROUPS`, and the 4 added filter slugs (`not-yet-due`, `legacy-delivered`, `legacy-referred`, `needs-review`). `STATE_FILTERS` restored to the original 4 slugs (confirmation-required, still-pregnant, unable-to-contact, resolved). `deriveState()` and all derivation helpers untouched.
- `app/Http/Controllers/PregnancyOutcomeController.php` (`index()`): removed the actionable/history bucket split, `$historyPaginator`, and `$showActive`/`$showHistory`/`stateFilterSlug`. Restored the original single `$paginator` over all rows (15/page). `rowFor()` unchanged.
- `resources/views/pregnancy-outcomes/index.blade.php`: restored the single-queue layout — search -> 4 summary cards -> concise "How to read this page" box -> one chip row (All + 4 filters) -> one desktop table + mobile cards -> pagination. Removed the hidden `state` input in the search form, the 4-bullet legend, the "Actionable"/"History & Review" chip groups, the Active Monitoring / Completed & Historical sections, and the `<details>` collapse. Phase 1/2 standardization (x-icon-title, x-flash, x-error-summary, x-status-badge, blue accents, `bg-primary` active chips) preserved.
- Deleted `resources/views/pregnancy-outcomes/_monitoring-rows.blade.php` (17F-only partial, no longer referenced).

### Restored original behavior
- One clean filter row: All, Outcome Confirmation Required, Still Pregnant — Confirmed, Unable to Contact, Confirmed Delivery. The remaining derived states (NOT_YET_DUE, LEGACY_DELIVERED, LEGACY_REFERRED, INVARIANT_VIOLATION) still render with their friendly labels in the table but no longer have filter chips.
- One monitoring table: historical/completed records (RESOLVED, LEGACY_*) appear dimmed and sort below actionable rows via the existing `STATE_ORDER`, so they never dominate the queue; the "Confirmed Delivery" chip filters resolved deliveries.
- Row actions unchanged: Confirm Still Pregnant, Unable to Contact, Open Profile / View Record (plus Pregnancy History for resolved/legacy-delivered), same routes and modal flow.
- `tests/Feature/PregnancyOutcomeMonitoringUiTest.php`: removed the 7 Sprint 17F tests (BB-BH); restored the original 25 tests (AB-AU), which all pass against the original behavior.

### Tests / results
- `php artisan test`: green aside from the 2 pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete).
- `npm run build`: succeeded (vite 7.3.1).
- `php artisan view:cache`: clean.
- `git diff --check`: clean.
- Confirmed via `git diff HEAD` that the controller, service, and tests diffs contain zero non-17F changes, and the view diff contains only the Phase 1/2 standardization (no 17F grouping artifacts).

Design defense: the monitoring page is scanned by staff in seconds; the 17F two-section model added cognitive load (group labels, extra chips, a collapsed history panel) for marginal benefit, and the original single-queue ordering already pushes actionable rows to the top. Reverting only the 17F additions keeps the earlier Phase 1/2 standardization and the 17D/17E feature intact. No clinical thresholds, derivation logic, routes, permissions, schema, or write flows were touched.

PHASE 17G MONITORING LIST UI SIMPLIFICATION COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17H — Prenatal Visits & Referral Management UI Cleanup

Status: Complete

Scope: UI-only (Blade/styling) cleanup of the Prenatal Visits index and the Referral Management index pages. No backend logic, controllers, services, models, routes, database schema, queries, calculations, or status behavior was changed.

### Prenatal Visits index (resources/views/prenatal_visits/index.blade.php)
- Visit Date and Next Visit now render on one straight line (whitespace-nowrap on the date cells); date formatting and the underlying date values are untouched.
- GA column: suffix changed from `wks` to `weeks` (e.g. `38 weeks`). When gestational_age is missing (it is a nullable field), the cell shows `—` instead of a bare `weeks`, so the suffix is never orphaned.
- Risk column: HIGH and LOW badges continue to use the shared `x-status-badge` component (consistent height, padding, font, and nowrap via the `.status-badge` base class). HIGH = danger/red, LOW = success/green, ASSESSMENT INCOMPLETE (a real risk_level value) = warning/amber. Badge alignment was tightened by making the cell nowrap so badges cannot wrap unpredictably. Risk values and assessment logic are unchanged.
- Table alignment: Patient column given a sensible min-width; Visit Date, BP, Weight, GA, Risk, Next Visit, and Actions headers/cells marked whitespace-nowrap so dates and GA never wrap. Column order and design are unchanged. BP cells additionally kept nowrap and mono font.

### Referral Management index (resources/views/referrals/index.blade.php)
- Removed the visible TOTAL summary card (``$total`` rendering removed from the UI only; the controller still passes the total and the backend count is untouched).
- The top status summary was rebuilt as 4 balanced, equal cells in a 2x2 grid (lg: 4-across): Pending (amber count, warning accent), Completed (green), Refused (orange), Cancelled (gray). Identical container, padding, alignment, and spacing for all four.
- Pending keeps a slight emphasis: amber count plus a small `Action Required` pill only when pending referrals exist (``$hasPendingAny``). The Pending cell uses the same card structure as the other three.
- The referral table, search box, status dropdown, View Referral, Print, Referral Analytics panel, charts, and month filter were not redesigned.

### Tests / results
- php artisan test: 704 passed, 2 failed — both are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete). No new failures.
- npm run build: succeeded (vite 7.3.1).
- php artisan view:cache: clean.
- git diff --check: clean.

Design defense: both pages now rely on the Phase 1/2 design tokens and the shared status-badge component, so badges render identically to the rest of the system. Keeping dates and GA on single lines prevents mid-value wrapping that can be misread during a quick scan, while a one-line `Aug 14, 2026` format is consistent with the rest of the UI. The GA fallback guard preserves the nullable gestational_age contract. On referrals, removing the TOTAL card and balancing the four status cells makes the queue state legible at a glance; Pending retains a subtle warning emphasis (pill) without a structurally distinct card, so the summary is uniform. No clinical thresholds, calculations, referral statuses, routes, or write flows were touched; this sprint was strictly presentational.

## Sprint 17H.1 — Prenatal Visits: Long Risk Text Wrapping Fix

Status: Complete

Scope: Small UI fix in the Prenatal Visits desktop table only. A long/unexpected risk value (e.g. ``THE SYSTEM CANNOT FIND THE PATH SPECIFIED.``) used to force the Risk cell onto one line and push the Actions column off-screen.

### Change
- Added a dedicated wrap variant to the shared badge CSS: ``.status-badge.status-badge-wrap`` (white-space normal, max-width 13rem, line-height 1.4, overflow-wrap break-word). Normal values (HIGH, LOW, ASSESSMENT INCOMPLETE) fit within the max-width, so they still render as one-line badges and their appearance is unchanged.
- The desktop Risk cell now applies ``status-badge-wrap`` to the badge and aligns it ``align-middle``, so long values wrap vertically inside the capped column instead of stretching the table.
- The Actions cell is pinned with ``whitespace-nowrap align-middle`` so the View/Edit/Delete icons stay on one row and remain visible.
- No truncation, no hidden values, no changes to stored risk_level, risk assessment, ML, controller, service, or database.

### Verification
- New regression test ``tests/Feature/PrenatalVisitsLongRiskUiTest.php`` (2 tests): confirms the long value renders in full with the wrap class, and View/Edit/Delete icons are all present for that row; and that HIGH/LOW/ASSESSMENT INCOMPLETE keep their normal badge variants.
- ``php artisan test``: 706 passed, 2 failed — the 2 are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete).
- ``npm run build``: succeeded. ``php artisan view:cache``: clean. ``git diff --check``: clean.

Design defense: capping the Risk badge width keeps the table from growing to fit an abnormal value, and wrapping (not truncating) preserves full clinical information. The wrap variant is opt-in via a single extra class on the shared badge, so the design-system badge behavior elsewhere is untouched. No clinical logic was modified.

PHASE 17H.1 PRENATAL VISITS LONG RISK WRAP FIX COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17I — Pregnancy Outcome Monitoring: Remove Pregnancy History Button from Confirmed Delivery Rows

Status: Complete

Scope: One UI-only change on the Pregnancy Outcome Monitoring page. The "Pregnancy History" button was removed from the Actions column of "Confirmed Delivery" (STATE_RESOLVED) rows in the desktop table. Those rows now show only "View Record".

### Change
- `resources/views/pregnancy-outcomes/index.blade.php`: the Pregnancy History button's visibility condition was narrowed from `STATE_RESOLVED` or `STATE_LEGACY_DELIVERED` to `STATE_LEGACY_DELIVERED` only. Confirmed Delivery (RESOLVED) rows therefore render only the "View Record" action; "Historical Delivered Record" (LEGACY_DELIVERED) rows keep the Pregnancy History button; every other monitoring state and its actions are untouched.
- No change to the Pregnancy History feature, its route (`patients.delivered.history`), controller, or page. No change to monitoring logic, deriveState(), confirmed-delivery logic, database, or backend behavior. No row/table redesign. Mobile cards were already without the button and are unchanged.

### Verification
- New regression tests in `tests/Feature/PregnancyOutcomeMonitoringUiTest.php` (BA, BB):
  - BA: a Confirmed Delivery row shows "View Record" with the `patients.show` link and does NOT show "Pregnancy History" or the `patients.delivered.history` link.
  - BB: a Historical Delivered Record row keeps the "Pregnancy History" button and its link.
- Monitoring UI suite: 27 passed (25 original + 2 new).
- `php artisan test`: 708 passed, 2 failed — the 2 are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete).
- `npm run build`: succeeded. `php artisan view:cache`: clean. `git diff --check`: clean.

Design defense: the Pregnancy History destination remains fully accessible from the delivered-history workflow and patient profile; the monitoring queue simply stops duplicating it on confirmed deliveries, where the View Record link is the primary action. Keeping the button for legacy delivered records preserves the historical presentation. This was a strictly presentational change; no clinical or routing behavior was modified.

PHASE 17I PREGNANCY OUTCOME MONITORING CONFIRMED DELIVERY ACTIONS COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17J — Patient Profile Context-Aware Back Button (from Pregnancy Outcome Monitoring)

Status: Complete

Scope: Navigation/UI improvement only. The hardcoded "View Monitoring Page" button on the patient profile's Pregnancy Outcome card is now a context-aware "Back" button that restores the exact Pregnancy Outcome Monitoring view the user came from (state filter, search, pagination page). The redundant "Pregnancy History" button inside the same card was removed for confirmed-delivery records. No monitoring logic, deriveState(), delivery logic, database, clinical rules, or assessment logic was touched.

### How the return URL is passed
- `resources/views/pregnancy-outcomes/index.blade.php`: the page builds one return URL per request — `route('pregnancy-outcomes.index', request()->query())` — which preserves every current monitoring query parameter (state slug, search, page, etc.). All four profile-opening links (desktop patient name, desktop "View Record"/"Open Profile", mobile patient name, mobile "View Record"/"Open Profile") now append it as `?return=<encoded monitoring URL>`.

### How it is validated
- `app/Http/Controllers/PatientController.php` (`show()`): the `return` query parameter is resolved through a new private helper `resolveMonitoringReturnUrl()`. It is accepted only when: it is a non-empty string; its scheme is http/https; its host matches the application host (from `route('pregnancy-outcomes.index')`); and its path is `/pregnancy-outcomes` (or the monitoring root). Anything else — missing, malformed, external, `javascript:`/non-http scheme, or an internal-but-wrong-path URL — is ignored and the safe fallback is used. No open-redirect vector is possible.

### What query/filter state is preserved
- `state` (e.g. `resolved`, `confirmation-required`, `still-pregnant`, `unable-to-contact`), `search`, `page`, and any other current monitoring query parameters survive the round trip and are restored when "Back" is clicked.

### Safe fallback
- `resources/views/patients/show.blade.php`: when no valid return URL is present (profile opened directly, or the return was rejected), the Back button falls back to the base `route('pregnancy-outcomes.index')` — the plain monitoring page, matching the previous hardcoded destination. The button is never broken.

### UI changes in the Pregnancy Outcome card
- The "Pregnancy History" button inside the card was removed (confirmed-delivery records now show only "Back"); the separate "View Pregnancy History" action in the normal patient header is unchanged, and the delivered-history page/route is untouched.
- The button label is simply "Back" using the existing `btn btn-secondary` style.

### Verification (tests/Feature/PregnancyOutcomeProfileBackNavigationTest.php, 12 tests)
- Monitoring links carry the full context (state + search + page) as a return URL; page 2 verified with a 20-patient dataset.
- Back restores each monitoring view: Confirmed Delivery (`resolved`), Outcome Confirmation Required (`confirmation-required`), Still Pregnant — Confirmed (`still-pregnant`), Unable to Contact (`unable-to-contact`).
- Search query and pagination page survive the profile round trip.
- Direct profile open uses the plain monitoring page fallback.
- External domain, `javascript:` scheme, and internal-but-wrong-path return URLs are all rejected with the fallback used.
- The card's Pregnancy History button is gone (delivered-history link appears exactly once — the header) while the header's "View Pregnancy History" remains; the delivered-history page still renders.
- `php artisan test`: 720 passed, 2 failed — the 2 are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete).
- `npm run build`: succeeded. `php artisan view:cache`: clean. `git diff --check`: clean.

Design defense: browser-history-only back links are unreliable (refresh, new tab, direct URL); an application-controlled return URL guarantees the exact monitoring context is restored. The validator keeps the return strictly internal and monitoring-scoped, so there is no open-redirect surface and direct profile access always gets a sensible destination. This sprint was purely navigational; no clinical or routing behavior changed.

PHASE 17J PATIENT PROFILE CONTEXT-AWARE BACK COMPLETE - AWAITING HUMAN ACCEPTANCE

## Sprint 17K — Risk Monitoring Dashboard Risk Type Selector and Analytics Reorder

Status: Complete

Scope: Dashboard analytics selection/display improvement only. The Risk Analytics section on the Risk Monitoring page now has a compact "Risk Type" selector (High Risk default / Low Risk) sitting beside the existing Month selector. The same single trend chart is reused to show either the monthly HIGH or monthly LOW assessment trend, and its title changes dynamically to "High-Risk Patients" or "Low-Risk Patients". The Risk Analytics section (selectors + trend chart + Highest High-Risk Month / Most Common Condition summary cards) now sits above the other analytics graphs, which were moved into their own "Risk Analytics Breakdown" section below it. No clinical rules, classification, ML behavior, assessment completeness, decision-source logic, patient/prenatal records, database schema, or migrations were touched.

### Backend changes
- `app/Services/RiskAnalyticsService.php` (`get()`): now accepts an optional `?string $riskType`. Only `HIGH`/`LOW` are allowed; anything else (including `null`) defaults to `HIGH`. The response gains `riskType`, `riskTrend` (the series for the selected type), and `lowRiskTrend`. Existing keys (`highRiskTrend`, `riskDistribution`, `conditions`, `bpFollowUp`, `summary`) are preserved unchanged so the breakdown charts and summary cards behave exactly as before.
- `app/Http/Controllers/RiskMonitoringController.php`: new private `riskTypeFilter()` helper — only `LOW` is honored, everything else defaults to `HIGH`. Both `index()` and the JSON `analytics()` endpoint read `risk_type` and forward it to the service.

### Frontend changes (`resources/views/risk/monitoring.blade.php`)
- Risk Analytics header now holds `Risk Type [High Risk ▼]` beside `Month [All Months ▼]`, sharing the same form/select styling and wrapping together on small screens.
- The trend chart title is dynamic (`High-Risk Patients` / `Low-Risk Patients`; the fixed "by Month" suffix is gone) and the line color follows the selected type (red for HIGH, emerald for LOW).
- The trend chart + summary cards stay in the main "Risk Analytics" section; Risk Distribution by Month, Maternal Conditions by Month, and BP Follow-Up by Month now render below in a new "Risk Analytics Breakdown" panel — still above the patient list.
- The Month selector keeps working and composes with Risk Type; both fire the same `loadAnalytics()` fetch (now sending `risk_type` too).
- The HIGH/LOW/ASSESSMENT INCOMPLETE summary cards, the statistics counters, the patient list, and the system-info card are unchanged.

### Verification (tests/Feature/RiskAnalyticsRiskTypeTest.php, 7 tests)
- Default risk type is HIGH and `riskTrend` equals `highRiskTrend`; LOW returns the low trend; any other value (e.g. `MEDIUM`) is rejected and defaults to HIGH.
- Month + risk type compose correctly in the service and via the JSON endpoint.
- The monitoring page renders the selector defaulting to High Risk, the dynamic title, and the reordered sections (trend before breakdown; breakdown contains the distribution/BP charts).
- Summary cards and counters remain present.
- `php artisan test`: 727 passed, 2 failed — the 2 are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete). Full RiskAnalyticsTest suite still green.
- `npm run build`: succeeded. `php artisan view:cache`: clean. `git diff --check`: clean.

Design defense: reusing the single trend chart with a data/type switch keeps the dashboard uncluttered and avoids duplicating Chart.js instances, while limiting `risk_type` to HIGH/LOW keeps the selector strictly a visualization choice — it can never affect risk classification or assessment output. Keeping the summary cards on the HIGH series preserves their existing meaning ("Highest High-Risk Month"), and isolating the other graphs into their own section lets the primary trend chart surface first without removing any existing analytics.

## Sprint 18 — In-App Notifications for Clinical Events + Profile Access/Header Hardening

Status: Complete

Scope: Notification infrastructure + two defensive work streams. Part 1 adds a database-backed in-app notification system driven by real persisted clinical events: an urgent BP visit, a repeat-BP-pending visit, referral creation, and referral closure each generate exactly one notification per recipient — never on page render. Part 2 hardens the authenticated header so the user-menu link always resolves to the logged-in user's own profile (with explicit regression tests that staff cannot escalate to admin via profile/password tampering).

### Backend changes
- `database/migrations/2026_08_20_000001_create_notifications_table.php`: the standard `notifications` table (id, type, notifiable morph, data, read_at, timestamps) with indexes on `notifiable_type`/`notifiable_id`/`read_at`.
- `app/Notifications/{UrgentBloodPressureNotification,PendingRepeatBloodPressureNotification,ReferralCreatedNotification,ReferralClosedNotification}.php`: database-channel (`['database']`) notifications only; each builds a structured `data` payload (`type`, `title`, `message`, `action_label`, `destination` route name + parameters) so tray links are resolved at render time with current routing, never stored absolute URLs.
- `app/Services/SystemNotificationService.php`: single entry point for generating notifications strictly from post-persistence events. `adminRecipients()` targets all active admins; `clinicalRecipients()` targets the patient's assigned staff (when assigned) plus admins. Fix: recipient builder returns `Illuminate\Support\Collection` (the original `Eloquent\Collection` return type caused a live `TypeError` — `collect()->unique()` yields a base collection — that silently aborted the send; surfaced by the trigger tests and corrected).
- `app/Http/Controllers/NotificationController.php`: `markAsRead` and `markAllAsRead` are self-scoped — they operate only on the authenticated user's own notifications via `where('id', ...)->where('notifiable...')` guards. Both under `auth` middleware; unauthorized/foreign targets 404.
- `app/Http/Controllers/PrenatalVisitController.php` (`store`): after the transaction commits, a visit with `urgency === 'URGENT_CLINICAL_REVIEW'` fires `notifyUrgentBloodPressure` and a `bp_verification_status === 'PENDING_REPEAT'` fires `notifyPendingRepeatBloodPressure`. Firing only in `store` (never from `update`, which already guards each state, and never from any render) keeps the one-event guarantee.
- `app/Http/Controllers/ReferralController.php`: `store` notifies admins of a newly created pending referral (`notifyReferralCreated`, acting staff excluded); `complete`/`refuse`/`cancel` notify admins of terminal closure (`notifyReferralClosed`).
- `app/View/Composers/NotificationComposer.php` + `app/Providers/AppServiceProvider.php`: registers the generic (non-cached) View Composer so the dashboard tray gets `notifications` (latest 8) and `unreadNotificationCount` without per-sprint controller leaks. Reverted from cached (`view()->composer`) because cached profiles/dashboard record stale models.

### Frontend changes
- `resources/views/layouts/app.blade.php` header: the dead notification `<a href="#">` was replaced with a real `<button id="notifBell">` (no scroll-to-top fake). The profile dropdown "Account" link now always points to the current user (no fixed `/users/<id>` or `href="#"` placeholders), keeping admins and staff signed in to their own profile.

### Verification (tests/Feature/NotificationTest.php 13 tests, ProfileAccessHeaderTest.php 11 tests)
- Display/scoping: users see only their own unread count, own tray rows, and read/read-all mutations affect only the authenticated user; guest access to notification routes redirects to login; zero-notification state renders safely; dashboard renders never create/duplicate notifications.
- Event triggers: urgent 165/110 visit POST notifies assigned staff + admins exactly once (both urgent and pending-repeat); non-urgent visit notifies nobody; an already-urgent `update` does not duplicate; referral creation notifies admins but not the acting staff; referral closure notifies admins.
- Profile access: admin/staff reach their own profile; guest blocked; profile and password updates preserve the caller's role and cannot escalate a staff user to admin; profile update changes only the current authenticated user; email uniqueness preserves the user's own address; header links to the authenticated user's profile; logout behavior unchanged.
- Found and fixed during testing: the `SystemNotificationService` collection return-type `TypeError` described above.
- `php artisan test`: 751 passed, 2 failed — the 2 are the pre-existing unrelated baseline failures (ExampleTest guest 302-vs-200, ProfileTest soft-delete), unchanged and documented since Sprint 17K. `php artisan view:cache`: clean. `php -l` clean on every touched file.

Design defense: notifications are written only from post-persist hooks so the dashboard is a pure read path — re-renders can never multiply notifications and `Notification::fake()` unit tests can assert exact-once semantics. Tray links store route names + parameters (not URLs) so destinations stay correct if routing changes. The bell stays an inert-but-visible button (no client behavior) rather than a fake link, and the user menu resolves the profile URL at render from the authenticated user to remove any staff->admin or wrong-user navigation path. All recipients are server-derived (assigned staff + role-checked admins), so acting staff and foreign users can never be targeted or forged.

## Sprint 19 — Staff Account Ownership / Administration Boundary

Status: Complete

Scope: Enforce and prove the Admin-only account lifecycle. Staff never administers accounts — it can only manage its own name/email/password on `/profile`. All Manage Staff routes (index/create/store/edit/update/destroy) are Admin-only at the controller level; self-service account deletion through the generic profile page is removed at both the UI and route layers; staff creation keeps `role` pinned server-side to `staff`; staff edit cannot touch role; and profile updates cannot modify role or account-administration fields.

### Backend changes
- `app/Http/Controllers/StaffController.php`: already guarded — every route (`index`, `create`, `store`, `edit`, `update`, `destroy`) begins with `checkAdmin()` (guest or non-admin → `403`). Re-verified unchanged. `store()` validates name/email, password `min:6`, hashes with `Hash::make`, and hard-codes `role => 'staff'` (never reads `role` from the request); `update()` only applies `name`/`email` (with uniqueness ignoring the current id) and never exposes a role field; `destroy()` is Admin-only and soft-deletes.
- `app/Http/Controllers/ProfileController.php`: `destroy()` (the Breeze self-delete endpoint) removed; the unused `Auth` import dropped.
- `routes/web.php`: `DELETE /profile` (`profile.destroy`) removed. The `/profile` path still serves GET (edit) and PATCH (update); a DELETE request now returns `405 Method Not Allowed`, so neither Staff nor Admin can self-delete through the generic profile page, and no legitimate feature depends on that route.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`: unchanged — login still routes admin/staff to the dashboard.
- Self-service password change remains available only through the existing Breeze `PUT /password` (`PasswordController`), which acts strictly on the authenticated user and cannot touch role.

### Frontend changes
- `resources/views/profile/partials/delete-user-form.blade.php`: partial deleted.
- `resources/views/profile/edit.blade.php`: the "Delete Account" card removed entirely; the profile page now offers only Update Profile Information and Update Password.
- Sidebar: the "Manage Staff" item was already admin-only (`auth()->user()->role === 'admin'`); kept as-is — the controller `checkAdmin()` is the authoritative control, the hidden sidebar link is only cosmetic.

### Verification (tests/Feature/StaffAccountAdministrationBoundaryTest.php, 13 tests; ProfileTest reworked)
1. Admin can open Manage Staff (`staff.index` → 200, renders staff rows).
2. Staff receives `403` opening Manage Staff.
3. Admin can create a Staff account (`staff.store` → 302 + persisted `role = staff`).
4. Submitted `role=admin` during staff creation still yields a `staff` account and does not increase the admin count.
5. Created password is hashed (`Hash::check` passes; plaintext ≠ stored).
6. Duplicate email is rejected (`assertSessionHasErrors('email')`, count unchanged).
7. Staff cannot access `staff.create` (403).
8. Staff cannot call `staff.store` (403, and the attacker's user row is never created).
9. Staff cannot edit another staff account (403 on GET and PUT; target unchanged).
10. Staff cannot delete another staff account (403; target still exists).
11. Staff cannot delete their own account through `/profile` (`405`, still authenticated, row intact).
12. Admin can still remove Staff through Manage Staff (`staff.destroy` → 302 + `assertSoftDeleted`).
13. Profile update cannot modify role or account-administration fields (`role=admin`/`approved`/`is_administrator` posted → role stays `staff`, admin count stays 0).
- `ProfileTest` reworked: the two stock Breeze delete tests replaced with "self-service account deletion disabled at the route layer" (staff) and "disabled for admins too" — both assert `405`, still authenticated, row intact. This also removes the long-standing ProfileTest soft-delete baseline failure.
- `php artisan test`: 765 passed, 1 failed — the single remaining failure is the pre-existing unrelated `ExampleTest` (guest `GET /` 302-vs-200), unchanged since earlier sprints. `php artisan view:cache`: clean. `php -l` clean on every touched file.

Design defense: the boundary is enforced at the controller (authorization) and route (method) layers, never by hiding menu links — a direct request from a staff session to any staff resource route hits `checkAdmin()` and returns `403`. Staff creation pins `role => 'staff'` server-side and only reads `name`/`email`/`password` from the request, so privileged-field tampering is structurally impossible; `create()` uses explicit attribute arrays instead of mass assignment of arbitrary request data. Because self-deletion is not part of the intended account lifecycle (removal is exclusive to Admin via Manage Staff), removing the endpoint entirely is safer than guarding it: there is no reachable code path by which Staff — or an Admin distracted on the generic profile page — could self-delete, while Admin's authorized Manage Staff deletion is untouched and covered by a test. Profile updates go through `ProfileUpdateRequest`, which validates only `name`/`email`, so roles and any future account-administration columns can never be written from profile/password requests.

## Sprint 19B — Profile UI/UX Redesign (Presentation Only)

Status: Complete

Scope: UI/UX redesign of `/profile` only. The Sprint 18/19 backend and security behavior is untouched — the same routes, controllers, forms, and validation handle the same submissions exactly as before. The default Laravel/Breeze appearance (dark charcoal cards, generic spacing) was replaced with the application's light, centered design language.

### Backend / Route / Database / Security changes
None. No controller, route, model, middleware, migration, service, or authorization logic changed. The self-scoped profile update (`PATCH /profile`), password update (`PUT /password`), role-escalation protection, and Admin-only staff administration remain byte-for-byte identical.

### Frontend changes (3 Blade presentation files only)
- `resources/views/profile/edit.blade.php`: sets the layout header to `Profile` (topbar title + `Home › Profile` breadcrumb), centers the workspace in a `max-w-3xl` column, and adds a display-only Profile Summary card (initials avatar, full name, email, and a subtle `STAFF`/`ADMIN` status badge). A small vanilla-JS block drives the show/hide password toggles for all three password fields (independent, `type="button"`, keyboard accessible with `aria-label`/`aria-pressed`).
- `resources/views/profile/partials/update-profile-information-form.blade.php`: card restyled to the system language (`bg-white rounded-2xl border-gray-100 shadow-sm`, `bg-gray-50` section header) — "Account Information / Update your personal account details." Full Name + Email Address sit side-by-side on desktop (`sm:grid-cols-2`) and stack on mobile. Inputs use the app's `h-10`, focus ring, and per-field error state (subtle red border + readable error text + `aria-describedby`). Button relabelled to "Save Changes". Success shows a green "✓ Profile information updated successfully." chip using the existing `session('status')` and an Alpine fade, never `alert()`.
- `resources/views/profile/partials/update-password-form.blade.php`: card restyled as "Password & Security / Keep your account protected with a secure password." Current Password occupies a full row; New Password and Confirm New Password share a row on desktop and stack on mobile. All three inputs get an inline eye toggle that switches `password` ↔ `text` independently without submitting. Button relabelled to "Update Password". Success chip "✓ Password updated successfully." The dead Breeze email-verification block (User does not implement `MustVerifyEmail`) was dropped.

### Verification
- Relevant suites green: ProfileTest (5), ProfileAccessHeaderTest (11), StaffAccountAdministrationBoundaryTest (13), NotificationTest (13) — 42 passed, 126 assertions.
- `php artisan test`: 765 passed, 1 failed — the single failure is the pre-existing unrelated `ExampleTest` (guest `GET /` 302-vs-200), identical to the Sprint 19 baseline; not touched.
- `php artisan view:cache`: clean. `git diff --check`: clean. No migrations run.

Design defense: the redesign reuses the application's own tokens and components (`bg-white`/`border-gray-100` cards, `bg-gray-50` card headers, `status-badge`, `btn-primary`, `input-label`/`input-error`, primary `#2563eb` focus ring) rather than inventing a new system. The page stays a read/self-service surface: role is rendered as a badge only (no role input), self-deletion remains absent, and every form still posts to its existing named route (`profile.update`, `password.update`) with unchanged validation. Password visibility is pure client-side presentation — the backend still receives the same `type="password"` field values — so no validation or security behavior changes. Inputs are intentionally explicit (controlled error-border classes) so the required field-level validation UX is deterministic, while labels/errors still come from the shared components.
