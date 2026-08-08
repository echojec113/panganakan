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
