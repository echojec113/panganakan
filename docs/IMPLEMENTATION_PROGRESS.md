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

> **Superseded by Sprint 13 (dashboard scope decision).** Patch 2's dashboard filtering was reverted by explicit product decision: `assigned_staff_id` remains for patient ownership, assigned-staff display, the My Patients filter, and accountability, but Staff dashboard statistics are now clinic-wide and match Admin. See the Sprint 13 entry below.

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

## Sprint 9 — Clinical Factor Matrix (Documentation)

Status: Complete

### Objective

Create a formal Clinical Factor Matrix that traces every prenatal risk factor from the eight source documents (DOCU 0–7) through the current Laravel implementation and into proposed future logic — without modifying any application code.

### Constraint

No PHP, Blade, Python, routes, models, controllers, services, tests, or migrations were modified. Only documentation files were created or updated.

### What Was Created

**`docs/CLINICAL_FACTOR_MATRIX.md`** (70 KB, 1,770 lines) — a formal 8-section document:

**Section 1 — System Decision Hierarchy**
- Current hierarchy (COMPLETENESS → RULE_BASED → MACHINE_LEARNING → final integration) rendered as an ASCII flow chart
- Document-proposed hierarchy from DOCU 4 Part 12: Emergency/urgent trigger → deterministic HIGH → ML HIGH → incomplete → LOW — requiring a new pre-completeness assessment for severe BP (BP-URG) and warning symptoms

**Section 2 — Clinical Factor Matrix** (16 individual factor entries)
Every entry structured with: FACTOR ID, DB fields, validation, current engine behavior, test coverage, proposed rule (from source docs), boundary cases, and clinical-approval-needed field.

| ID | Factor | Source |
|----|--------|--------|
| AGE-Y | Adolescent pregnancy (age < 19) | DOCU 4 |
| AGE-A | Advanced maternal age (35+ first pregnancy) | DOCU 4 |
| BP-H | Elevated blood pressure (>=140/90) | DOCU 4, DOCU 5 |
| BP-URG | Severely elevated BP (>=160/110 urgent) | DOCU 4, DOCU 5 |
| DM-01 | Diabetes in pregnancy | DOCU 4 |
| AN-01 | Maternal anemia | DOCU 4 |
| CS-01 | Previous cesarean delivery | DOCU 4 |
| RM-03 | Recurrent miscarriage (>=3) | DOCU 4 |
| US-P01 | Abnormal fetal presentation | DOCU 4 |
| US-AF01 | Amniotic fluid abnormality | DOCU 4 |
| US-FH01 | Fetal heartbeat abnormality | DOCU 4 |
| MAT-WARN | Maternal warning symptoms | DOCU 1, DOCU 4 |
| ML-HIGH | Machine-learning HIGH prediction | DOCU 3, DOCU 4 |
| ML-LOW | Machine-learning LOW prediction | DOCU 4 |
| INC-PATH | Assessment incomplete path | DOCU 4 |
| COMP-01 | Required record completeness | DOCU 0, DOCU 4, DOCU 6 |

**Section 3 — Required Factor Groups Coverage Summary (A–K)**
- Maps all 11 factor groups from DOCU 4 Part 12 to current implementation
- Status per group: Fully Covered (5), Partially (4), Not Covered (2)
- Key gap: warning symptoms (group E) and medical-history conditions (group C sub-set) are stored but not evaluated by any clinical rule

**Section 4 — Cross-Factor Interactions**
- Documents the 8 interactions explicitly stated in DOCU 4 Part 12 (e.g. parity+age, CS+multiple gestation, PET+IUGR)
- Current implementation: all treated as independent evaluations
- Key gap: `completeness` checkbox in the current completion check means a visit can be marked "complete" before required labs are recorded, bypassing the deterministic rules

**Section 5 — Current Code Gaps (6 sub-sections)**
1. **Missing migrations** — `previous_cs`, `miscarriage` (Patient); `recommendation` (PrenatalVisit)
2. **Unused database fields** — `pusd`, `efw`, `liquor_volume` (Ultrasound model fields present in migration but never read by any clinical rule)
3. **Absent clinical rules** — Hb/anaemia lab-threshold rules, Doppler/CTG, labour/DELIVERY_DATE/EDD rules, post-term evaluation, outcome monitoring (DELIVERED LABOR)
4. **Machine-learning provenance gap** — `predict.py` accepted by the system but contains no data dictionary, no training cohort description, no validation metrics, no version manifest
5. **Known `$casts` vs migration mismatches** — fields expected as `boolean` or `integer` by the model but created as `text` or `varchar` in migrations
6. **TPHA/RPR test and PH communication gap** — Patient `is_ph` (boolean) exists but RPR-lab-positive flag is not generated by any rule

**Section 6 — Proposed Implementation Batches**
- Sprint 10: BP verification + urgency classification — BP-H (>=140/90) remains HIGH when deterministic evaluation is reached (PROMPT/REVIEW REQUIRED verification, no pre-completeness bypass); BP-URG (>=160/110) is the only proposed pre-completeness urgent safety evaluation, with missing-record information preserved and displayed. Urgency metadata and structured BP reason metadata added. Repeat-BP workflow fields (`repeat_bp_sys`, `repeat_bp_dia`, `repeat_bp_recorded_at`, `repeat_bp_recorded_by`, `bp_verification_status`) require a separate migration.
- Sprint 11: Warning-symptom evaluation (WARNING_SYMPTOM_YES), immediate referral
- Later batches: US GA context, Hb/anaemia rules, diabetes provenance labels, stage-aware completeness, outcome monitoring after EDD

**Section 7 — Clinical Approval Register** (16 entries)
- Every proposed rule change, migration, and workflow addition given a unique APPROVAL-ID
- Status: all 16 PENDING — every clinical automated rule requires qualified clinical reviewer approval before real-world deployment
- COMP-01 added (stage-aware and field-level completeness)

**Section 8 — Defense Summary** (7 statements)
- Reasoning for documentation-first approach, single-source-of-truth, factor-level granularity, cross-factor interaction exposure, code-gap transparency, approval-before-implementation, and regulatory-readiness posture

### What Was NOT Modified

- No PHP, Blade, Python, routes, models, controllers, services, or test files were changed
- No migrations were created or executed
- No database commands were run
- No `composer.json` or `package.json` dependencies were changed
- No configuration files were modified

### Clinical Safety

Zero impact. No clinical logic, assessment hierarchy, risk threshold, recommendation text, or decision path was modified.

### Known Issues

(Same as Sprint 8 — no new issues introduced)

- Pre-existing: `recommendation` column in PrenatalVisit $fillable but missing from migrations
- Pre-existing: `previous_cs` and `miscarriage` in Patient $fillable but missing from migrations
- Pre-existing: Referral feature test has 403 authorization failure
- Pre-existing: ProfileTest soft-delete mismatch
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0

## Sprint 10 — Blood-Pressure Verification and Urgency Classification

Status: Complete (see defence notes below)

### Objective

Implement BP verification workflow, urgency classification (BP-URG pre-completeness bypass), structured BP explainability, and repeat-BP UI across the CDSS without executing migrations, modifying Python/ML, or creating automatic referrals.

### Clinical Policies Approved

- BP-URG (>=160 systolic OR >=110 diastolic): immediate HIGH + URGENT_CLINICAL_REVIEW pre-completeness urgent safety evaluation. Missing records preserved alongside the BP-URG outcome.
- BP-H (>=140 systolic OR >=90 diastolic): HIGH in the deterministic rule step (post-completeness). Urgency = PROMPT.
- Four verification statuses: NOT_REQUIRED, PENDING_REPEAT, REPEAT_COMPLETED, UNABLE_TO_REPEAT.
- Both BP-H and BP-URG count in existing HIGH counts; BP-URG additionally appears in separate urgent-BP counters.
- Editing initial BP clears the repeat-pair (repeat_bp_sys, repeat_bp_dia, recorded_at, recorded_by).
- Audit logging for all BP actions (BP_REPEAT_RECORDED, BP_INITIAL_EDITED).
- Repeat BP validation: both-or-neither, sys > dia, same range limits (60-200 sys, 40-130 dia).

### Files Created

- `database/migrations/2026_08_01_000001_add_bp_verification_to_prenatal_visits.php` — additive migration (7 nullable columns: repeat_bp_sys, repeat_bp_dia, repeat_bp_recorded_at, repeat_bp_recorded_by FK→users, bp_verification_status, urgency, bp_assessment JSON). **NOT EXECUTED.**
- `app/Services/BloodPressureAssessmentService.php` — new service with `assess()`, `isInitialElevated()`, `isInitialSevere()`, `isRepeatSevere()`, `determineVerificationStatus()`. Returns structured array: triggered, reason_code (BP-H|BP-URG|null), risk_level, urgency (PROMPT|URGENT_CLINICAL_REVIEW|null), verification_status, threshold/label/interpretation/action text.
- `tests/Unit/Services/BloodPressureAssessmentServiceTest.php` — 15 tests covering normal, elevated, severe, repeat-BP resolved, pending repeat, unable to repeat, null safety, and boundary cases.

### Files Modified

**Backend Services**

- `app/Services/RiskAssessmentService.php` — Injected `BloodPressureAssessmentService`; implemented new 4-step decision hierarchy:
  1. **BP assessment** always runs first
  2. **BP-URG → immediate HIGH + URGENT_CLINICAL_REVIEW** (pre-completeness, missing records preserved)
  3. **Completeness → INCOMPLETE** (BP-H alert preserved if triggered)
  4. **BP-H + other rules → HIGH** (post-completeness)
  5. **ML** (only if complete + no deterministic HIGH)
- `app/Services/ClinicalRuleEngine.php` — Removed inline BP-H and BP-URG blocks (lines 23–29). All other non-BP rules preserved exactly.
- `app/ValueObjects/AssessmentResult.php` — Added `public readonly ?string $urgency` and `public readonly ?array $bp_assessment`; updated constructor (2 optional params, default null); APPROVED_KEYS from 10→12; toArray() includes both new keys.
- `app/Services/DecisionIntegrationService.php` — `decide()` accepts optional `?string $urgency` and `?array $bpAssessment`; passes them to all five `new AssessmentResult(...)` paths (null for ML paths, passes through for completeness/BP/high-risk paths).
- `app/Models/PrenatalVisit.php` — Added 7 fields to `$fillable`; casts for `repeat_bp_recorded_at => datetime` and `bp_assessment => array`; added `repeatBpRecordedBy()` belongsTo(User).

**Controllers**

- `app/Http/Controllers/PrenatalVisitController.php`:
  - `store()`: Validates repeat BP fields (both-or-neither, sys>dia), passes to RiskAssessmentService, persists repeat BP fields + urgency + bp_assessment, logs BP_REPEAT_RECORDED audit.
  - `update()`: Detects initial BP change → clears repeat pair + logs BP_INITIAL_EDITED audit. Reprises repeat BP validation and persistence, recalculates risk.
  - `recalculateIncompleteVisits()`: Passes existing repeat BP data + verification status to RiskAssessmentService; persists urgency and bp_assessment.
- `app/Http/Controllers/DashboardController.php`:
  - Admin: Added `$urgentBpCount`, `$pendingRepeatCount`, `$urgentBpPatients`, `$pendingRepeatPatients`.
  - Staff: Added `$staffUrgentBpCount`, `$staffPendingRepeatCount`.
- `app/Http/Controllers/RiskMonitoringController.php` — Added urgency filter (URGENT_CLINICAL_REVIEW, PROMPT), BP verification status filter (PENDING_REPEAT, REPEAT_COMPLETED, UNABLE_TO_REPEAT, NOT_REQUIRED), plus `$urgentBpCount` and `$pendingRepeatCount`.

**Blade Views**

- `resources/views/prenatal_visits/create.blade.php` — Added repeat BP verification section (conditional, shown when initial BP >=140/90), verification status dropdown, verification note textarea; JS to show/hide section and validate repeat BP.
- `resources/views/prenatal_visits/edit.blade.php` — Same as create, plus pre-populates existing repeat BP values; detects initial BP change and clears repeat pair.
- `resources/views/patients/show.blade.php` — Added urgency badge (URGENT in red animate-pulse) next to risk level; BP Assessment section in Risk Assessment Card (classification, interpretation, action); urgency and repeat BP in visit details dropdown.
- `resources/views/risk/monitoring.blade.php` — Added urgency and BP verification filter dropdowns; changed stats grid from 4-col to 6-col (added Urgent BP and Pending Repeat); added urgency badge in mobile and desktop views.
- `resources/views/dashboards/admin.blade.php` — Added Urgent BP Alerts and Pending Repeat BP cards in risk summary (changed to 6-col grid); added urgent BP and pending repeat lists in Priority Monitoring.
- `resources/views/dashboards/staff.blade.php` — Changed risk summary from 3-col to 5-col (added Urgent BP and Pending Repeat cards).
- `resources/views/exports/patient-record.blade.php` — Added urgency display line and BP Assessment section in Clinical Decision Summary.

**Tests**

- `tests/Unit/Services/BloodPressureAssessmentServiceTest.php` — 15 new tests covering all clinical policies.
- `tests/Unit/ValueObjects/AssessmentResultTest.php` — Updated "ten" to "twelve" in test names; added assertions for urgency and bp_assessment properties.
- `tests/Unit/Services/DecisionIntegrationServiceTest.php` — Added 3 new tests: completeness path accepts urgency and bp_assessment, rule-based path accepts bp_assessment, ML paths set both to null.
- `tests/Unit/Services/ClinicalRuleEngineTest.php` — Updated 3 BP tests to expect empty array (BP moved to BloodPressureAssessmentService); updated duplicate-removal test to exclude BP reason.

### Test Results

- PHP syntax check: clean (all 9 modified PHP files)
- **56 targeted tests pass** (ClinicalRuleEngine, AssessmentResult, DecisionIntegration, BloodPressureAssessment)
- **128 tests pass** in full suite (same 4 pre-existing failures)
- Zero regressions

### Design Defence

1. **BP-URG pre-completeness bypass**: Severe-range BP (>=160/110) is treated as a safety override because waiting for completeness could delay clinical attention. Missing records are preserved and displayed so data gaps are not hidden.

2. **BP-H post-completeness**: Elevated BP (>=140/90 but <160/110) does not bypass completeness. It is evaluated alongside other deterministic rules after completeness passes. This prevents false reassurance from incomplete data.

3. **Repeat-BP workflow**: Repeat BP fields use both-or-neither validation. The repeat measurement is always compared against the initial reading. If a repeat resolves the elevation, the service returns `triggered: false`. Editing initial BP clears the repeat pair (clinical policy: a new reading invalidates the old comparison).

4. **Structured BP assessment data**: `bp_assessment` is a JSON column storing the full structured result (reason_code, label, risk_level, triggered, threshold, interpretation, action, verification_status). This supports retrospective audit and future explainability improvements without schema changes.

5. **Urgency as separate metadata**: Urgency (URGENT_CLINICAL_REVIEW / PROMPT / null) is a dedicated column, not embedded in reasons. This allows separate UI treatment (red URGENT badge) and independent filtering in Risk Monitoring.

6. **Minimal migration (not executed)**: The additive migration uses only nullable columns. No existing data is affected. Manual inspection is required before execution.

### Known Issues

- Migration file `2026_08_01_000001_add_bp_verification_to_prenatal_visits.php` created but NOT executed — manual inspection required.
- Pre-existing: `recommendation` column in PrenatalVisit $fillable but missing from migrations.
- Pre-existing: `previous_cs` and `miscarriage` in Patient $fillable but missing from migrations.
- Pre-existing: Referral feature test has 403 authorization failure.
- Pre-existing: ProfileTest soft-delete mismatch.
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0.

## Sprint 10 Safety Corrections

Status: Complete

### Objective

Apply eight approved release-blocker corrections to the Sprint 10 BP verification workflow without changing clinical thresholds, the decision hierarchy, Python/ML, routes, or auth policy. All changes are server-authoritative: the client (Blade forms) can no longer override the system's verification decision.

### Corrections Applied

1. **BP-URG overrides completeness.** Severe-range BP (BP-URG, >=160/110) now resolves to HIGH + RULE_BASED + URGENT_CLINICAL_REVIEW even when required records are missing. Missing records are preserved so data gaps are not hidden.
2. **Severe repeat evaluated first.** A severe repeat reading (e.g. 165/110 after a normal 120/80 initial) now triggers BP-URG + URGENT_CLINICAL_REVIEW. Repeat measurements can escalate, not just confirm, the initial reading.
3. **Initial severe BP is always urgent.** Initial BP in the severe range returns BP-URG + URGENT_CLINICAL_REVIEW (previously it could return PROMPT), regardless of repeat outcome.
4. **Server-authoritative verification status.** `determineVerificationStatus()` derives status from actual data first: both repeat values present => REPEAT_COMPLETED; explicit UNABLE_TO_REPEAT with a non-empty note => UNABLE_TO_REPEAT; otherwise PENDING_REPEAT. Client-supplied PENDING_REPEAT, REPEAT_COMPLETED (without a repeat pair), and NOT_REQUIRED are ignored/re-derived.
5. **UNABLE_TO_REPEAT requires a note.** Controller validation (store + update) rejects UNABLE_TO_REPEAT with a blank/whitespace-only verification note. The service also only preserves the note when the computed status is UNABLE_TO_REPEAT.
6. **Initial BP edits clear stale repeat data.** When submitted initial BP differs from the stored value, the repeat pair (repeat_bp_sys/dia/recorded_at/by), a stale prefilled repeat pair, and the old verification status are cleared and re-derived in a single coherent persistence update. BP_INITIAL_EDITED audit log preserved.
7. **Approved UI wording.** Removed the hardcoded "15-30 mins" wait-time phrasing from create/edit forms; replaced with the approved protocol message ("Record a repeat measurement according to the clinic's approved protocol to verify."). Verification-status dropdown restricted to only the UNABLE_TO_REPEAT client-requestable option; PENDING_REPEAT/REPEAT_COMPLETED are derived server-side.
8. **Corrected the wrong existing test.** The `DecisionIntegrationServiceTest` case that asserted "BP-URG + missing records => ASSESSMENT INCOMPLETE" now asserts HIGH + RULE_BASED + URGENT_CLINICAL_REVIEW with missing_records preserved.

### Implementation Details

**`app/Services/BloodPressureAssessmentService.php`** (rewritten)

- Evaluation order: normalize pair -> initial severe -> repeat severe -> BP-URG (if either severe) -> initial elevated check -> not elevated (NOT_REQUIRED, no trigger) -> BP-H + PROMPT.
- Added `classifyRepeat()` returning repeat_interpretation: NOT_RECORDED / NORMAL / ELEVATED / SEVERE; `repeat_interpretation` included in every payload.
- `determineVerificationStatus()` is now public and server-authoritative (see Correction 4).
- BP-URG payload adds `effective_max_systolic` / `effective_max_diastolic` (max of initial + repeat) and label "Severe-range blood-pressure finding".
- `verification_note` only accepted when computed status is UNABLE_TO_REPEAT; trimmed.

**`app/Services/DecisionIntegrationService.php`**

- Added `decideUrgentBp()` returning HIGH + RULE_BASED + URGENT_CLINICAL_REVIEW with `missing_records` preserved, `bp_assessment` forwarded, `ml_prediction` null, `ml_valid` false.
- `decide()` short-circuits to `decideUrgentBp()` when the incoming `bp_assessment` has `reason_code === 'BP-URG'` (defense-in-depth so the urgent safety path is preserved even if completeness was checked first).

**`app/Services/RiskAssessmentService.php`**

- BP-URG now routes through `decideUrgentBp($missingRecords, [$bpResult['label']], $bpResult)` before the completeness branch. ML is never invoked on this path.

**`app/Http/Controllers/PrenatalVisitController.php`**

- Injected `BloodPressureAssessmentService` for the NOT_REQUIRED default constant.
- Repeat-BP systolic > diastolic checks now use `filled()` (store + update).
- UNABLE_TO_REPEAT note validation added in store + update.
- `store()` persists `bp_verification_status` from the server-derived `bp_assessment.verification_status` (default NOT_REQUIRED), plus `urgency` and `bp_assessment`.
- `update()` rewritten: initial-BP change detection clears stale repeat fields + ignores prefilled repeat values in that same request, verification status/note are nulled when initial BP changed, and all fields (clinical + repeat + risk + urgency + bp_assessment) persist in one coherent `update()` call.
- `recalculateInvisits()` persists server-derived `bp_verification_status` and reuses the stored `verification_note` from `bp_assessment` when re-running UNABLE_TO_REPEAT records.

**`resources/views/prenatal_visits/create.blade.php` / `edit.blade.php`**

- Removed "15-30 mins" phrasing; replaced with approved protocol wording.
- Verification-status dropdown now offers only `UNABLE_TO_REPEAT` (plus empty default); added hint that status is derived automatically.

**`database/migrations/2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table.php`** (new, additive)

- Adds nullable `notes` and `recommendation` columns to `prenatal_visits` (both columns are written by the controller but were missing from the migration set - pre-existing drift). Uses `Schema::hasColumn()` guards so it is safe whether or not either column already exists; `down()` only drops existing columns. **NOT EXECUTED.**

### Tests

- `tests/Unit/Services/BloodPressureAssessmentServiceTest.php` - 23 tests (was 15). New coverage: severe-repeat-first (BP-URG from a severe repeat after normal initial), forged REPEAT_COMPLETED falls back to PENDING_REPEAT, UNABLE_TO_REPEAT requires a note, repeat classification (NOT_RECORDED/NORMAL/ELEVATED/SEVERE), and updated UNABLE_TO_REPEAT tests to pass a note.
- `tests/Unit/Services/DecisionIntegrationServiceTest.php` - corrected the BP-URG + missing-records expectation (Correction 8).
- `tests/Unit/Services/RiskAssessmentServiceTest.php` (new) - 10 orchestrator tests: BP-URG overrides completeness (case 1), initial severe + normal repeat stays urgent (case 2), normal initial + severe repeat is BP-URG (case 3), BP-H + missing records stays INCOMPLETE (case 8), BP-H complete => HIGH RULE_BASED (case 9), BP-H overrides ML LOW (case 10), BP-H overrides ML HIGH (case 11), ML never invoked on BP-URG, UNABLE_TO_REPEAT requires note, forged REPEAT_COMPLETED resolves to PENDING_REPEAT.
- `tests/Feature/Sprint10BloodPressureCorrectionsTest.php` (new) - 6 controller-level tests: UNABLE note required on store + update, BP-URG store persists HIGH + URGENT + PENDING_REPEAT, BP-H store persists PROMPT + PENDING_REPEAT, initial-BP edit clears stale repeat pair + derives NOT_REQUIRED, forged REPEAT_COMPLETED on update derives PENDING_REPEAT.

### Test Results

- PHP syntax check: clean on all changed PHP files.
- **57 focused tests pass** (BloodPressureAssessment 23 + DecisionIntegration 18 + RiskAssessment 10 + Sprint10 feature 6).
- Full suite: **152 pass**, 4 pre-existing failures (ExampleTest guest redirect, PatientPhilhealthTest 403, ProfileTest soft-delete, RiskMonitoringStatusTest 403) - zero new regressions.
- Testing database: in-memory SQLite (`:memory:`).

### Records Modified

- `app/Services/BloodPressureAssessmentService.php`
- `app/Services/DecisionIntegrationService.php`
- `app/Services/RiskAssessmentService.php`
- `app/Http/Controllers/PrenatalVisitController.php`
- `resources/views/prenatal_visits/create.blade.php`
- `resources/views/prenatal_visits/edit.blade.php`
- `database/migrations/2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table.php` (new, not executed)
- `tests/Unit/Services/BloodPressureAssessmentServiceTest.php`
- `tests/Unit/Services/DecisionIntegrationServiceTest.php`
- `tests/Unit/Services/RiskAssessmentServiceTest.php` (new)
- `tests/Feature/Sprint10BloodPressureCorrectionsTest.php` (new)
- `docs/IMPLEMENTATION_PROGRESS.md` (this entry)

### Records NOT Modified

- `app/Services/ClinicalRuleEngine.php` - untouched (BP rules remain extracted).
- Machine-learning service / Python files - untouched.
- Routes, auth/authorization middleware - untouched.
- Clinical thresholds (BP-H 140/90, BP-URG 160/110) - unchanged.
- Models (except PrenatalVisit fillable/casts from original Sprint 10) - untouched.
- No `php artisan migrate` executed; both Sprint 10 migrations remain unexecuted on the dev DB.

### Known Issues

- Migrations `2026_08_01_000001_add_bp_verification_to_prenatal_visits.php` and `2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table.php` created but NOT executed - manual inspection required.
- Pre-existing: `previous_cs` and `miscarriage` in Patient $fillable but missing from migrations.
- Pre-existing: Referral feature test has 403 authorization failure.
- Pre-existing: ProfileTest soft-delete mismatch.
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0.

## Sprint 10 Hardening Patch

Status: Complete

### Objective

Apply seven final release-hardening corrections to the Sprint 10 BP workflow. No migrations executed; no clinical thresholds, ML/Python, routes, or auth changed.

### Corrections Applied

1. **Non-destructive rollback.** `2026_08_01_000002` `down()` is now an intentional no-op with an explanatory comment. `notes`/`recommendation` predate the migration (already present in production), so rollback must never drop historical clinical data.
2. **BP label always surfaced.** `decideUrgentBp()` always appends the `bp_assessment.label` to reasons/rule_reasons, even when the caller supplies no rule reasons, preventing an empty "Risk factors identified:" assessment.
3. **Strict ML-never-invoked tests.** Added `ml is never invoked on the complete bp-h path` to `RiskAssessmentServiceTest` (alongside the existing BP-URG one), both using `shouldReceive('predict')->never()`.
4. **Controller status validation restricted.** `bp_verification_status` validation in store + update now allows only `UNABLE_TO_REPEAT` (or absent). `PENDING_REPEAT` / `REPEAT_COMPLETED` are rejected and remain server-derived.
5. **Edit form note source fixed.** `edit.blade.php` now loads the verification note from `$visit->bp_assessment['verification_note']` instead of the nonexistent `bp_verification_note` model column.
6. **Neutral severe-range wording.** `clinical_interpretation` for BP-URG changed from "requires immediate repeat measurement..." to "The recorded reading met the severe-range screening threshold and requires urgent qualified clinical review."
7. **Removed unapproved transport instruction.** BP-URG `suggested_action` changed from "...Ensure transport, receiving facility, and handover according to clinic protocol." to "Immediate qualified assessment and referral evaluation are recommended according to clinic protocol."

### Files Modified

- `database/migrations/2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table.php` (down() no-op)
- `app/Services/DecisionIntegrationService.php` (label always in reasons/rule_reasons)
- `app/Http/Controllers/PrenatalVisitController.php` (status validation restricted to UNABLE_TO_REPEAT, store + update)
- `app/Services/BloodPressureAssessmentService.php` (clinical_interpretation + suggested_action wording)
- `resources/views/prenatal_visits/edit.blade.php` (note from bp_assessment)
- `tests/Unit/Services/DecisionIntegrationServiceTest.php` (new label-inclusion test)
- `tests/Unit/Services/RiskAssessmentServiceTest.php` (ML-never-invoked on complete BP-H)
- `tests/Feature/Sprint10BloodPressureCorrectionsTest.php` (forged-status test now asserts validation rejection; added no-status update derivation test)
- `docs/IMPLEMENTATION_PROGRESS.md` (this entry)

### Test Results

- PHP syntax check: clean on all changed PHP files.
- Focused Sprint 10 tests: **60 pass** (BloodPressureAssessment 23, DecisionIntegration 19, RiskAssessment 11, Sprint10 feature 7).
- Full suite: 152 pass, same 4 pre-existing failures (ExampleTest guest redirect, PatientPhilhealthTest 403, ProfileTest soft-delete, RiskMonitoringStatusTest 403) - zero new regressions.
- `git diff --check`: clean.
- Testing database: in-memory SQLite (`:memory:`).

## Sprint 10.1 — Latest-Assessment Retrieval Fix and CDSS Risk UI Redesign

Status: Complete

### Objective

Two-part sprint on the patient profile (`patients.show`), strictly retrieval + presentation. No migrations, no clinical thresholds, no ML/Python, no routes, no auth changes.

1. **Retrieval correctness:** The profile picked the latest visit by `visit_date` only (`sortByDesc('visit_date')->first()`), so two visits sharing the same visit date (common in active clinics) could surface an older, lower-risk assessment. The selection now resolves ties deterministically newest-first: `created_at DESC` then `id DESC`.
2. **UI redesign:** Replace the compact "Risk Factors" block with a prominent Risk Assessment panel that summarizes the full CDSS decision in one place, using plain-clinical-friendly wording throughout.

### Changes

1. **`app/Http/Controllers/PatientController.php` — `show()`.** Latest assessment is now resolved server-side via `$patient->prenatalVisits()->orderByDesc('created_at')->orderByDesc('id')->first()` and passed to the view as `$latestAssessment`. The `prenatalVisits` relation is re-sorted newest-first (same tie-breaker) so the history table is deterministic. `medicalHistory`/`ultrasound`/`birthPlan` flags unchanged.
2. **`resources/views/patients/show.blade.php` — Risk Assessment panel redesign.** The old "Risk factors identified" card is replaced by a full CDSS summary card:

   - **Status hero** — colored banner: red "HIGH RISK" (with a white "URGENT CLINICAL REVIEW" pill when `urgency` is set), green "LOW RISK", amber "ASSESSMENT INCOMPLETE", or a gray "NO ASSESSMENT AVAILABLE" empty state (with an Add First Visit link for ONGOING patients). Labels shown verbatim.
   - **Decision source** — friendly badge per source: "Rule-Based Clinical Assessment", "Machine Learning Assessment", "Required Records Check", "ML Assessment Unavailable", or "Legacy Assessment".
   - **Clinical summary** — assessment text + prominent recommendation + next visit date + assessment date.
   - **Blood-pressure card** — initial/repeat readings with friendly labels: verification "Not Required"/"Repeat Pending"/"Repeat Completed"/"Unable to Repeat"; repeat interpretation "Not Recorded"/"Normal Range"/"Elevated Range"/"Severe Range"; urgency "Urgent Clinical Review"/"Prompt Clinical Review". Labels never assert a diagnosis. Guarded with `is_array($latestAssessment->bp_assessment)` for legacy data.
   - **Triggered factors** — chips from `rule_reasons` ∪ `risk_reasons` via `ListNormalizer`; a BP-URG visit prepends its BP label chip; HIGH with no factors shows "No structured clinical factors recorded."
   - **Required records** — amber "Required Records Still Missing" section for `missing_records`.
   - **Machine-learning display** — shows a valid prediction only on `MACHINE_LEARNING` results; on `RULE_BASED`/`COMPLETENESS` shows "Machine learning was not used for the final decision." (with the deterministic rule / incomplete-records variant); on invalid ML shows "Machine learning output was unavailable or invalid."
   - **Decision flow** — per-source summary lines (factors found / completeness / ML verdict) mirroring Risk Monitoring.
   - History-table urgency wording "PROMPT (within 1 week)" replaced with "Prompt Clinical Review".
3. **Tests** — new `tests/Feature/PatientProfileRiskPanelTest.php` (9 scenarios: newest-wins same visit date, rule-based HIGH, BP-URG urgent, stale ML hidden, ML LOW, completeness incomplete, legacy plain-string rule reasons, null `bp_assessment`, no-visit empty state). `tests/Feature/LegacyPatientShowRenderingTest.php` fallback assertion updated to the new panel wording.

### Design Notes

- Retrieval is resolved in the controller so the rule is testable in isolation and the view stays presentation-only.
- `created_at DESC, id DESC` guarantees a stable, deterministic winner even for ties, without inventing a "visit serial" column (no migration).
- The ML verdict is only rendered when it was the actual decision source, preventing stale `ml_prediction` from previous rows being shown as if it applied to the current result.
- All urgency/verification wording is descriptive of the workflow, not a diagnosis, preserving patient-safety and explainability rules.

### Files Modified

- `app/Http/Controllers/PatientController.php`
- `resources/views/patients/show.blade.php`
- `tests/Feature/PatientProfileRiskPanelTest.php` (new)
- `tests/Feature/LegacyPatientShowRenderingTest.php` (fallback wording)
- `docs/IMPLEMENTATION_PROGRESS.md` (this entry)

### Test Results

- PHP syntax check: clean on all changed PHP files.
- Focused suites: `PatientProfileRiskPanelTest` 9, `LegacyPatientShowRenderingTest` 6, `ExplainabilitySprint7Test` 21 (includes existing patient-profile explainability regression test), `StaffAccessControlTest` 8, `Sprint10BloodPressureCorrectionsTest` 10, `DeliveredPatientWorkflowTest` 2, unit services 53 — all pass.
- Full suite: **173 pass / 4 fail** (562 assertions) — same 4 pre-existing failures (ExampleTest guest redirect, PatientPhilhealthTest 403, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403); zero new regressions.
- `git diff --check`: clean.

## Post-Sprint 10.1 — PhilHealth Data-Integrity Patch (small)

Status: Complete

### Objective

Small data-integrity patch: enforce the rule that a PhilHealth number may only be persisted for a PhilHealth member. No clinical assessment, BP, migration, route, Python/ML, referral, or unrelated patient-field changes.

### Changes

1. **`PatientController::store()`** — builds patient data from `$request->validated()` instead of `$request->all()`, converts `philhealth_member` to boolean via `$request->boolean()`, and forces `philhealth_number` to `null` whenever membership is false. A submitted number for a non-member is never persisted.
2. **`PatientController::update()`** — same rule. Changing an existing member to non-member clears the stored number from the database (even when the form omits the field). When membership stays true, the existing `required_if:philhealth_member,1` validation still requires a number, so the number is preserved.
3. **`tests/Feature/PatientPhilhealthTest.php`** — rebuilt with a staff user (the old test authenticated a factory user without a role and therefore never reached `store`; it was one of the four pre-existing 403 failures). Now covers:
   - create non-member with submitted number → stored number is `null`
   - update member → non-member → old number cleared
   - member with valid number → number preserved on update
   - member without required number → validation fails and stored number unchanged

### Design Notes

- `$request->validated()` guarantees persistence uses only approved inputs; `$request->all()` is no longer used for patient creation.
- Boolean normalization happens in the controller so the stored flag is always a real boolean regardless of form string values.
- The null-forcing rule is applied after validation, so the `required_if` membership rule still governs member validation and the integrity rule governs persistence.

### Files Modified

- `app/Http/Controllers/PatientController.php`
- `tests/Feature/PatientPhilhealthTest.php`
- `docs/IMPLEMENTATION_PROGRESS.md` (this entry)

### Known Gaps (preserved)

- **Delivered-patient prenatal-visit protection:** there is still no protection preventing creation/editing of prenatal visits for patients whose status is no longer ONGOING (no policy; only `auth` + `staff` middleware). This remains an open gap for a future sprint and is unchanged by this patch.
- Migrations `2026_08_01_000001_add_bp_verification_to_prenatal_visits.php` and `2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table.php` created but NOT executed - manual inspection required.
- Pre-existing: `previous_cs` and `miscarriage` in Patient `$fillable` but missing from migrations.
- Pre-existing: ProfileTest soft-delete mismatch; RiskMonitoringStatusTest referral 403.
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0.

### Test Results

- PHP syntax check: clean on `app/Http/Controllers/PatientController.php`.
- Focused PhilHealth tests: 4 pass (previously 1 failing with 403 due to missing staff role in the test).
- Full suite: **173 pass / 3 fail** (562 assertions) — the PatientPhilhealthTest 403 is resolved; remaining failures are the 3 pre-existing, unrelated ones (ExampleTest guest redirect, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403).
- `git diff --check`: clean.

## Final Consistency Patch (post-Sprint 10.1)

Status: Complete

### Objective

Two small consistency fixes. No migrations executed; no clinical rules, BP thresholds, ML/Python, routes, authorization, referrals, or unrelated UI changed.

### Changes

1. **`tests/Feature/PatientPhilhealthTest.php` — future-proofed test-only schema setup.** The `previous_cs`/`miscarriage` columns are now added to the in-memory test schema only when `Schema::hasColumn()` reports they are missing, so the tests keep working after the eventual real migrations add them (the documented `previous_cs`/`miscarriage` migration gap).
2. **Latest-visit selection consistency in exports.** `PatientController::show()`, `downloadPatientCsv()`, and `downloadPatientPdf()` all previously selected the latest visit by `visit_date` only (the exports used `sortByDesc('visit_date')`), which was inconsistent with the deterministic `created_at DESC, id DESC` ordering applied to the profile in Sprint 10.1. A single private helper now owns the selection:

   ```php
   private function latestPrenatalVisit(Patient $patient): ?PrenatalVisit
   {
       return $patient->prenatalVisits()
           ->orderByDesc('created_at')
           ->orderByDesc('id')
           ->first();
   }
   ```

   `show()`, `downloadPatientCsv()`, and `downloadPatientPdf()` all reuse it, so the profile, CSV export, and PDF export resolve the identical newest assessment when multiple visits share a `visit_date`.
3. **Focused tests** — new `tests/Feature/PatientExportConsistencyTest.php` (3 scenarios with two visits sharing the same `visit_date`):
   - profile displays the newer visit
   - CSV export embeds the newer visit's assessment (asserted on response content, not raw PDF text)
   - PDF export passes the newer visit to `exports.patient-record` (verified by capturing the `dompdf.wrapper` view data via a container stub, avoiding unreliable PDF binary assertions)

### Files Modified

- `app/Http/Controllers/PatientController.php`
- `tests/Feature/PatientPhilhealthTest.php`
- `tests/Feature/PatientExportConsistencyTest.php` (new)
- `docs/IMPLEMENTATION_PROGRESS.md` (this entry)

### Test Results

- PHP syntax check: clean on `app/Http/Controllers/PatientController.php`.
- Focused suites: `PatientExportConsistencyTest` 3, `PatientPhilhealthTest` 4, `PatientProfileRiskPanelTest` 9, `LegacyPatientShowRenderingTest` 6 — all pass.
- Full suite: **180 pass / 3 fail** (588 assertions) — same 3 pre-existing, unrelated failures (ExampleTest guest redirect, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403); zero new regressions.
- `git diff --check`: clean.

## Sprint 11 — Medical History Scope Stabilization, Data Integrity, and CDSS Input Governance

Status: Complete

### Objective

Stabilize the Medical History feature as a scoped, integrity-safe clinical record. Explicitly govern which fields the CDSS may consume. **No new clinical rules were added.** No migrations were created or executed. Routes, BP thresholds, the `BloodPressureAssessmentService`/`DecisionIntegrationService`/`RiskAssessmentService` decision hierarchy, and Python/ML were untouched.

### CDSS Input Allowlist (documented + locked by tests)

1. **Only `diabetes` and `anemia` are CDSS-active** from Medical History. The `ClinicalRuleEngine` reads them from prenatal-visit assessment inputs, not from Medical History directly — `recalculateIncompleteVisits()` passes `$visit->diabetes`/`$visit->anemia` (`PrenatalVisitController`), so the visit checkboxes are the source of truth for the engine. This pre-existing "source of truth ambiguity" (see matrix DM-01 note) is now explicitly documented and pinned by tests.
2. **The four legacy warning-symptom fields (`severe_headache`, `visual_disturbance`, `chest_pain`, `shortness_breath`) are confirmed record-only.** They are stored and displayed with an "Informational only — never used in the risk assessment" label and are never evaluated by any clinical service.
3. **All other fields (epilepsy, hypertension, asthma, thyroid_disease, heart_disease, liver_disease, smoking, allergies, drug_intake, std_history, breast_mass, mental_health_condition, other_specify) are record-only** background factors.
4. **The completeness gate is existence-based**, not content-based. A Medical History record satisfies the gate regardless of which checkboxes are set. Verified by test.

### Data-Integrity Changes

5. **Validated `store()`/`update()`.** Uses `$request->validate()` return value (the base `Request` has no `validated()`), normalizes every checkbox with `$request->boolean()` (unchecked → `false`), and stores `other_specify` as `required_if:other,1`, nulled whenever the `other` checkbox is unchecked.
6. **`patient_id` is preserved on update.** The update route never changes a record's patient; a posted `patient_id` is ignored.
7. **Duplicate prevention (application-level, no migration).** `create()` and `store()` redirect to the existing record's edit page with: "A Medical History record already exists for this pregnancy." The DB has no unique constraint; prevention is enforced in the controller.
8. **Delivered-patient protection.** `create()`, `store()`, `edit()`, and `update()` reject delivered patients via the existing `Patient::isDelivered()` pattern (no new trait; the previously assumed `app/Traits/BlocksDeliveredPatientActions.php` does not exist in this codebase).

### Service Extraction

9. **New `app/Services/PatientAssessmentRecalculationService.php`.** The `recalculateIncompleteVisits()` body moved out of `PrenatalVisitController` (which now keeps a thin delegate for API compatibility). `MedicalHistoryController`, `UltrasoundController`, and `BirthPlanController` now inject the service directly — all `app(PrenatalVisitController::class)` controller-to-controller calls were removed, aligning with AGENTS.md ("Prefer small services over large controllers").

### UI/UX

10. **Scoped, grouped forms.** `medical_histories/create.blade.php` and `edit.blade.php` now render four labeled groups (CDSS-Active Factors / Chronic & Background Conditions / Lifestyle, History & Physical Findings / Warning Symptoms & Notes) with a banner explaining the allowlist. The patient profile's Medical History section groups the same way with scope notes.
11. **`old()` preservation on validation errors.** The edit form distinguishes a validation-failed re-render (`old('_token') !== null`) so an unchecked box stays unchecked instead of reverting to the stored value.
12. **Stable form id `medical-history-form`.** The edit confirmation modal's `submitUpdateForm()` previously used `document.querySelector('form')`, which could target the layout's logout form; it now submits the explicit id.

### Files Modified

- `app/Http/Controllers/MedicalHistoryController.php` (rewritten: validation, allowlist constant, duplicate + delivered guards, service injection)
- `app/Http/Controllers/PrenatalVisitController.php` (constructor + thin delegate)
- `app/Http/Controllers/UltrasoundController.php` (service injection)
- `app/Http/Controllers/BirthPlanController.php` (service injection)
- `app/Services/PatientAssessmentRecalculationService.php` (new)
- `resources/views/medical_histories/create.blade.php` (grouped + scoped)
- `resources/views/medical_histories/edit.blade.php` (grouped + scoped + stable form id)
- `resources/views/patients/show.blade.php` (error flash + grouped Medical History section)
- `tests/Feature/MedicalHistoryScopeTest.php` (new — 16 scenarios)
- `tests/Unit/Services/PatientAssessmentRecalculationServiceTest.php` (new — 3 scenarios)
- `tests/Unit/Services/ClinicalRuleEngineTest.php` (2 new allowlist tests)
- `docs/IMPLEMENTATION_PROGRESS.md`, `docs/CLINICAL_FACTOR_MATRIX.md` (this entry)

### Test Results

- PHP syntax check: clean on all changed controllers/services.
- Blade compile: `php artisan view:cache` clean.
- Focused suites: `MedicalHistoryScopeTest` 16, `PatientAssessmentRecalculationServiceTest` 3, `ClinicalRuleEngineTest` 15 — all pass.
- Full suite: **203 pass / 3 fail** (674 assertions) — the same 3 pre-existing, unrelated failures (ExampleTest guest redirect, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403); zero new regressions.
- `git diff --check`: clean.

### Design Defense

Scope labeling ("Only Diabetes and Anemia affect the risk assessment") keeps the clinical user informed that the four warning-symptom checkboxes are documentation, not triggers, preventing the false belief that checking them raised a HIGH. Enforcing the allowlist at both the engine and the view layers protects explainability: every displayed reason maps to a documented evaluated factor. Duplicate prevention and delivered-patient guards enforce record integrity without a migration, which would have required a manual production review.

## Sprint 11 Hardening Patch — Source-of-Truth Wording, One-Way Visit→History Sync, and Recalculation Safety

### Objective

Stabilize the source-of-truth boundary between the dated Prenatal Visit and the pregnancy-level Medical History record, correct UI wording that overclaimed Medical History's role, and make auto-recalculation strictly protect finalized (HIGH/LOW) and delivered-patient visits.

### Decisions (recorded and locked by tests)

1. **Prenatal Visit is the source of truth** for the dated diabetes/anemia CDSS inputs; Medical History is pregnancy-level background documentation plus completeness evidence.
2. **One-way monotonic sync**, limited to `diabetes` and `anemia`: a confirmed visit Yes may set the background history value to true; a visit No never clears a true history value.
3. **No auto-create**: a missing Medical History is never created by the sync; the visit still stores its assessment without the background update.
4. **Clearing a pregnancy-level condition** requires explicit staff editing of the Medical History record; no diagnosis is inferred by the sync.
5. **New service**: `App\Services\MedicalHistoryConditionSyncService::syncConfirmedVisitConditions(Patient $patient, bool $diabetes, bool $anemia, ?PrenatalVisit $visit = null)` returns `changed`, `updated_fields`, `skipped_reason`, `visit_id`. Saves only when a value actually changes.
6. **Sync placement**: after successful visit persistence, inside the existing store/update transaction, using persisted visit values. Never invoked from the risk-assessment path; never triggers an assessment.
7. **Audit**: `MEDICAL_HISTORY_SYNC` entry written only when the history actually changes ("Medical History {diabetes, anemia} updated from prenatal visit ID: {id}"). No audit when nothing changed or when no history exists.
8. **Recalculation safety**: `PatientAssessmentRecalculationService::recalculateIncompleteVisits()` loads the patient first (no-op if missing or DELIVERED), requires all three records, and recalculates only `risk_level = 'ASSESSMENT INCOMPLETE'` visits. HIGH and LOW are historical and never rewritten.
9. **Preservation on recalculation**: repeat-BP pair, verification status/note, BP assessment metadata, and an existing `next_visit_date` are passed through and preserved.
10. **Wording**: "CDSS-Active Factors" → "Conditions Also Assessed During Prenatal Visits"; "Warning Symptoms & Notes" → "Legacy Historical or Recurring Concerns"; permanent "never used in the risk assessment" phrasing removed (future visit-level warning-symptom workflow pending approval).
11. **Patient profile**: diabetes/anemia presented as pregnancy-level background updates from confirmed prenatal visits; optional note shown when a visit recorded a condition but no Medical History exists (derived from the already-loaded `prenatalVisits` relation).
12. **No migrations, no threshold/rule changes**: the patch is implementation-only; no schema, BP, or CDSS-rule changes.
13. **Delivered-patient sync guard**: `MedicalHistoryConditionSyncService::syncConfirmedVisitConditions()` checks `$patient->isDelivered()` before any Medical History lookup; delivered pregnancies are never modified by visit synchronization (`skipped_reason = 'PATIENT_DELIVERED'`, no audit, no recalculation). Synchronization is disabled for completed pregnancies.

### Files Modified (this patch)

- `app/Services/MedicalHistoryConditionSyncService.php` (new)
- `app/Services/PatientAssessmentRecalculationService.php` (safety guards)
- `app/Http/Controllers/PrenatalVisitController.php` (sync inside store/update transactions + audit)
- `app/Http/Controllers/MedicalHistoryController.php` (source-of-truth comments)
- `resources/views/medical_histories/create.blade.php` + `edit.blade.php` (headings + banner)
- `resources/views/prenatal_visits/create.blade.php` + `edit.blade.php` (one-way sync note)
- `resources/views/patients/show.blade.php` (profile wording + optional missing-history note)
- `tests/Unit/Services/MedicalHistoryConditionSyncServiceTest.php` (new, 13 tests incl. delivered-patient guard)
- `tests/Feature/PrenatalVisitConditionSyncTest.php` (new, 10 tests)
- `tests/Unit/Services/PatientAssessmentRecalculationServiceTest.php` (9 tests)
- `tests/Feature/MedicalHistoryScopeTest.php` (wording assertions + prenatal page coverage)
- `tests/Unit/Services/ClinicalRuleEngineTest.php` (test renamed to "clinical rule engine consumes visit diabetes and anemia inputs only")

### Test Results

Focused suites all green. Full suite: **233 passed, 3 failed** — the three failures are the documented pre-existing ones (ExampleTest guest redirect, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403), unrelated to this patch.

### Design Defense

Keeping the sync monotonic and one-way prevents a negative visit from silently erasing a staff-confirmed background condition, which would corrupt the pregnancy record and the patient's perceived continuity of care. Running the sync inside the visit's own transaction guarantees either the visit and its background update persist together or neither does. Restricting recalculation to ASSESSMENT INCOMPLETE visits protects the historical explainability of already-finalized HIGH/LOW assessments while still completing interrupted assessments once all required records exist.

## Schema Reconciliation — Execute Pending BP Verification Migrations

Status: Complete

### Objective

Execute the two authored-but-pending migrations on the dev MySQL database so the live `prenatal_visits` schema matches the code that has been writing and querying the BP verification fields since Sprint 10.

### Background (verified)

The codebase (model `$fillable`/`$casts`, `PrenatalVisitController::store()/update()`, `PatientAssessmentRecalculationService`, `DashboardController`, `RiskMonitoringController`, and the Blade views) has consistently read/written `urgency`, `bp_verification_status`, `bp_assessment`, and `repeat_bp_*` since Sprint 10. Those columns existed only in the in-memory SQLite test database (where all migration files run) and did NOT exist in the live MySQL `prenatal_visits` table. Consequences confirmed before this change:

- `DashboardController` and `RiskMonitoringController` queries using `WHERE urgency = 'URGENT_CLINICAL_REVIEW'` or `WHERE bp_verification_status = 'PENDING_REPEAT'` threw `SQLSTATE[42S22]: Column not found`.
- Saving or updating a prenatal visit (which persists these fields) failed on the live DB.
- The two `2026_08_01_*` migration files were present but absent from the `migrations` table (unlike `notes`/`recommendation`, which had been reconciled manually).

### Changes

Executed via `php artisan migrate` (batch 17):

- `2026_08_01_000001_add_bp_verification_to_prenatal_visits` — adds `repeat_bp_sys`, `repeat_bp_dia`, `repeat_bp_recorded_at`, `repeat_bp_recorded_by` (FK → users, nullOnDelete), `bp_verification_status` (varchar 30), `urgency` (varchar 30), `bp_assessment` (json). All nullable.
- `2026_08_01_000002_add_notes_and_recommendation_to_prenatal_visits_table` — `hasColumn`-guarded reconciliation; no-op on this DB because `notes`/`recommendation` already exist (live `notes` remains `varchar(255)`, not `text`, and is left untouched).

### Verification

- `SHOW COLUMNS` confirms all seven BP columns now exist on the live `prenatal_visits` table.
- Both migrations recorded in the `migrations` table (batch 17).
- Dashboard queries (`urgency` / `bp_verification_status`) now execute without error, returning 0 for all historical rows — correct, since no BP-URG/PENDING_REPEAT data was ever persisted before.
- All 32 existing prenatal visits are untouched; no backfill performed (correctly, historical rows are non-urgent).

### Test Results

Full suite: **231 passed, 5 failed** (744 assertions).

- 3 pre-existing, unrelated failures: ExampleTest guest redirect (302), ProfileTest soft-delete, RiskMonitoringStatusTest referral 403. Unchanged.
- 2 `MachineLearningServiceTest` integration failures (`predict` with real low-risk/high-risk profiles → `valid` false): **environment issue, not a schema regression.** `.env` `PYTHON_PATH=C:\Users\BJ\maternity-system\venv\Scripts\python.exe` does not exist on this machine (no `C:\Users\BJ` directory), so `MachineLearningService::resolvePython()` falls back to the system Python (3.14.6), which lacks `joblib`/`pandas`/`scikit-learn`; `predict.py` emits a `ModuleNotFoundError` traceback and the structured result is invalid. This is unrelated to the migration and was failing on this machine before execution. Fix requires a working Python env with the ML packages and a correct `PYTHON_PATH` in `.env` — not modified in this sprint.

### Design Notes

- This was a pure schema reconciliation: no code, clinical logic, thresholds, decision hierarchy, or UI were modified.
- The `urgency` column is the canonical source for urgent-clinical-review identification; no replacement field or string-matching fallback was introduced.
- Historical rows intentionally remain non-urgent; the Urgent BP / Pending Repeat dashboards populate for new visits only.

### Files Modified

- `docs/IMPLEMENTATION_PROGRESS.md` — this entry

### Records NOT Modified

- No code (services, controllers, models, routes, views) modified.
- No clinical thresholds or decision hierarchy changed.
- No `.env` changes.
- No Python files changed.

## Sprint 12 — Risk & Referral Analytics (Chart.js, DB-Driven)

### Objective

Add a professional, database-driven analytics section to the existing Risk Monitoring (`/risk-monitoring`) and Referral Management (`/referrals`) pages using the same Chart.js 4.4.0 CDN pattern already established on the Admin Dashboard. Charts render only real DB data (no hardcoded values) and live **only** on their respective pages. Admin/Staff dashboards, tables, filters, search, Print, and Complete actions are untouched.

### Approved Decisions

1. **Cleared BP follow-up**: `bp_verification_status = 'REPEAT_COMPLETED'` AND `repeat_bp_sys < 140` AND `repeat_bp_dia < 90`. `NOT_REQUIRED` is deliberately excluded.
2. **Aggregation approach**: PHP bucketing over the bounded latest-visit row set (portable across MySQL and the SQLite test suite). The latest-visit-per-patient selection stays in SQL (`MAX(id) ... GROUP BY patient_id ... deleted_at IS NULL`), identical to the page's KPI counts.
3. **Monthly-only, rolling 12 months**: every chart and summary uses a single monthly dataset — the latest 12 calendar months ending at the most recent data point. Gaps are zero-filled so the trend stays continuous; if data spans fewer than 12 months, only the available months are shown in chronological order. The dataset is the same for all charts on a page, so every number reconciles.

### New Files

- `app/Services/AnalyticsService.php` — abstract base: `monthlySeries()` (rolling 12-month zero-filled monthly buckets, used by Risk only), `monthsInYear()` (Jan–Dec keys/labels for a year), `maxPeriod` (ties → earliest), `normalizeLabel`/`groupedTop` (trim + collapse whitespace + case-insensitive grouping, first-seen display label, top-N cap), `mostCommon`.
- `app/Services/RiskAnalyticsService.php` — `get()` returns `labels`, `highRiskTrend`, and per-month keyed arrays `riskDistribution{high[],low[],incomplete[]}`, `conditions{Hypertension[],Diabetes[],Anemia[]}` (unique patients per condition per month, no double count), `bpFollowUp{urgent[],pendingRepeat[],cleared[]}` (one value per month), plus `summary{highestHighRiskPeriod, mostCommonCondition}`. `highestHighRiskPeriod` is the busiest month in the trend (via `maxPeriod`); `mostCommonCondition` is computed from the totals of the same monthly arrays. Canonical constants mirror `BloodPressureAssessmentService` (`URGENT_CLINICAL_REVIEW`, `PENDING_REPEAT`, `REPEAT_COMPLETED`).
- `app/Services/ReferralAnalyticsService.php` — `get(?int $year = null, ?int $month = null)` returns `year`, `month`, `availableYears`/`availableMonths` (real DB values), `labels`, `referralTrend`, `statusTrend{pending,completed}`, `destinations[]` (top 8), `reasons[]` (top 8), `summary{mostReferredHospital, completionRate, busiestPeriod, mostCommonReason}` — all scoped to the filtered year/month window, so "Most Referred Hospital", "Completion Rate", "Most Common Reason", and the Top Destinations/Reasons rankings all reflect the filtered period. Completion rate = `completed/(pending+completed)×100` (0-divide guard; `Cancelled` excluded from the denominator, consistent with the page's Pending/Completed KPI cards). `referrals` has no `deleted_at`, so no soft-delete filter is applied.
- `tests/Feature/RiskAnalyticsTest.php` (13 tests) and `tests/Feature/ReferralAnalyticsTest.php` (14 tests).

### Modified Files

- `app/Http/Controllers/RiskMonitoringController.php` — constructor-injected `RiskAnalyticsService`; `index()` passes the monthly analytics array (no period parameter, no JSON endpoint).
- `app/Http/Controllers/ReferralController.php` — constructor-injected `ReferralAnalyticsService`; `index()` passes the default (latest-year, All Months) analytics array; `analytics()` returns the JSON payload for the month/year filter.
- `resources/views/risk/monitoring.blade.php` — analytics section inserted between the KPI grid and the Patient Assessments table: full-width High-Risk trend line chart → 2 summary cards (Highest High-Risk Month, Most Common Condition (Last 12 months)) → 2-col pair (Risk Distribution by Month + Maternal Conditions by Month, grouped bar charts) → full-width BP Follow-Up by Month grouped bar. No period selector. Uses the page's Tailwind design system; all canvases 260px, equal card heights, stacks below `lg`.
- `resources/views/referrals/index.blade.php` — analytics section between the 3 KPI cards and the table card, using the page's CSS-variable design system: centered page wrapper (no duplicate sidebar margin), compact Month/Year filter in the section header, 4 equal-height summary cards → two 2-col chart rows (Referrals by Month line + Pending vs Completed grouped bar; Top Destinations + Referral Reasons horizontal bars). Responsive classes collapse 4→2→1 summary cards and 2→1 chart columns via media queries. Selected-month mode swaps the Busiest Month card to "Selected Month Referrals".

### Chart.js Lifecycle

Both pages include `<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js">` (same 4.4.0 CDN as the Admin Dashboard) plus an inline script that registers chart instances per canvas. The Risk Monitoring page renders the server-passed analytics on load only. The Referral page also renders server-passed analytics on load and additionally fetches `referrals.analytics` when the Month/Year selects change, re-rendering charts and summary cards with a loading indicator (table search/status forms are unaffected). Both pages show empty-state messages (canvas hidden) when a dataset is empty — no NaN/undefined, no divide-by-zero, no console errors. Palette/tooltip/fonts mirror the dashboard (`#2563eb`, `#059669`, `#d97706`, `#7c3aed`, `#dc2626`, DM Sans 12px, tooltip `#0f172a`).

### Counting Rules Enforced

- Latest assessment per patient only (matches `highRiskCount`/`lowRiskCount`/`incompleteCount` KPI counts).
- Soft-deleted visits excluded (`deleted_at IS NULL`), matching the page.
- Structured fields only — no text matching (risk level, urgency, BP status, visit boolean columns).
- Garbage legacy risk strings (e.g., the historical Python-path error saved as a risk level) match no canonical level and are excluded from the distribution, same treatment the page table already gives them.
- Destinations/reasons normalized in PHP only (trim, collapse internal spaces, case-insensitive) for analytics; stored DB values are never modified (verified by test).

### Refinement (approved): Monthly-Only, Rolling 12 Months

- The Quarterly/Yearly aggregation, the period selector, and the `/analytics` JSON endpoints were removed from both pages and controllers. All aggregation is now **monthly only**.
- Every chart on a page uses the **same monthly dataset** (latest 12 calendar months ending at the most recent data point, zero-filled, chronological), so all charts and summaries stay consistent with one another.
- Referral destinations, reasons, most-referred hospital, and completion rate are now scoped to the same rolling 12-month window (previously all-time aggregates); the labels read "(Last 12 months)".

### Refinement (approved): Referral Month/Year Filter, Centering, and Alignment

**Scope:** Referral Management page only. Risk Monitoring analytics are untouched.

- **Month/Year filter.** The referral analytics header now has a compact Month dropdown (All Months + real months with data) and a Year dropdown (distinct years descending, from real `referral_date` values — no hardcoded lists). A JSON endpoint `GET /referrals/analytics?year=&month=` (restored at `routes/web.php`, auth group) serves the filtered dataset via `ReferralController::analytics()`; the page fetches it on select change (with a `Loading&hellip;` indicator) and re-renders charts without reloading. No value is selected → `null`, which falls back to the latest year with data (or `now()->year` when there is no data) and All Months. The referral table's search/status forms are separate GET requests and remain unaffected.
- **`ReferralAnalyticsService::get(?int $year = null, ?int $month = null)`** is now year-scoped: All Months returns a zero-filled 12-month series for the year (Jan–Dec via the new `AnalyticsService::monthsInYear()`); a selected month returns a single `M Y` bucket. `availableYears`/`availableMonths` (ascending, from real dates) drive the dropdowns. Destinations, reasons, completion rate, and the busiest period are all computed over the filtered year/month window. `monthlySeries()` is now used only by `RiskAnalyticsService`.
- **Centering fix.** The page wrapper previously stacked its own `margin-left: var(--sidebar-width)` on top of the app layout's existing sidebar offset, pushing content too far right. It now uses a centered container (`max-width: 1200px; margin-inline: auto`) matching the Risk Monitoring page's centering.
- **Balanced layout.** The analytics section now reads: 4 summary cards in one row (Most Referred Hospital, Completion Rate, Busiest Month, Most Common Reason) → two 2-col chart rows (Referrals by Month + Pending vs Completed, then Top Destinations + Referral Reasons). All cards are equal-height/equal-width via CSS (`ra-box` flex column, `.ra-chart-card`), canvases stay 260px, titles wrap cleanly, and the existing media queries collapse 4→2→1 summary cards and 2→1 chart columns (1100px/768px). "(Last 12 months)" suffixes were removed from all labels.
- **Selected-month summary card.** With a month selected, the "Busiest Month" card becomes "Selected Month Referrals" showing the month and its referral count (`referralSummaryBusiestSub`). With All Months it reverts to "Busiest Month".
- **Empty states.** A month with no data renders a single zero-filled bucket with no chart, "—" summary values, and a 0.0% completion rate — no NaN/undefined, no console errors.

### Refinement (approved): Single Month Filter — Current Calendar Year (both pages)

**Scope:** Risk Monitoring and Referral Management analytics. Admin/Staff dashboards untouched.

- **One Month dropdown only.** Both analytics headers now have a single compact Month dropdown: **All Months** + **January…December** (always all 12, rendered statically in Blade — never derived from the database). The **Year dropdown was removed entirely** from both pages (UI, JS state, query params, controller/service year handling, and `availableYears`/`availableMonths`). No unused or hidden year field remains.
- **Current calendar year is automatic.** Each service resolves `$year = (int) Carbon::now()->year` server-side; no year is hardcoded, no year parameter flows anywhere. The scope rolls to the new year automatically on January 1.
- **All Months** → the calendar axis `Jan…Dec` for the current year (always 12 labels in order, zero-filled; empty months are kept). **Specific month** → a single `M Y` bucket (`Jul 2026`) filtered to `referral_date` / `visit_date` in that month **and** the current year. The DB remains the source of truth; no dummy records are created.
- **JSON endpoints (no full reload):** `GET /risk-monitoring/analytics?month=all|1..12` (new) and `GET /referrals/analytics?month=all|1..12` (updated). Month input is validated on the server: `all`, empty/missing, or anything outside 1–12 safely defaults to All Months — invalid strings never reach the queries. `index()` also reads the validated `month` so a reload keeps the selection.
- **No-data behavior.** A month with no records stays selectable. Charts hide/clear (canvases destroyed before re-render — no stale values), summaries show "—", Completion Rate shows 0%, and the empty-state text reads **"No risk analytics data for the selected month."** / **"No referral data for the selected month."** (All Months keeps the neutral messages). No NaN/undefined, no errors.
- **Selected-month summary labels.** Risk: "Highest High-Risk Month" → **"Selected Month"** (+ HIGH-count sub-line). Referral: "Busiest Month" → **"Selected Month Referrals"** (month + referral count). All Months restores the original labels. Risk "Most Common Condition (Last 12 months)" → **"Most Common Condition"**.
- **New helper:** `AnalyticsService::monthBuckets(int $year, ?int $month)` returns the 12-key short-label axis (`Jan`…`Dec`) or a single `Y-m`/`M Y` bucket. `RiskAnalyticsService::get(?int $month = null)` and `ReferralAnalyticsService::get(?int $month = null)` both use it; `latestAssessments()` is year/month-filtered; `monthlySeries()`/`monthsInYear()` are no longer used by either service.
- **Referral page design.** The extra light-blue panel (`background: var(--bg-base)` on the page wrapper, painted inside the app's white `page-shell`) was removed. The wrapper now uses the same centered container as the other tabs (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8`) — no duplicated sidebar margin, no second colored background. Visual order: title → 3 KPI cards → analytics header (Month dropdown beside the title) → 4 summary cards → 2-col chart rows → search/status → table, all sharing one centered width with equal side margins and left-aligned labels. KPI grid is now responsive (`ra-grid-3`: 3 → 2 → 1); summary cards `ra-grid-4` (4 → 2 → 1) and charts `ra-grid-2` (2 → 1) retain their media queries; the month dropdown wraps below the heading on mobile.

### Test Results

- New suites: `RiskAnalyticsTest` 16 passed, `ReferralAnalyticsTest` 14 passed (30 tests, 141 assertions).
- Full suite: **261 passed, 5 failed** (885 assertions). The 5 failures are the documented pre-existing ones: 2 `MachineLearningServiceTest` integration failures (machine lacks the `C:\Users\BJ\...\venv` Python from `.env`; `joblib` `ModuleNotFoundError`) and 3 doc-linked failures (ExampleTest guest redirect 302, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403). **Zero new regressions.**

### Known Pre-Existing Note (not introduced here)

`RiskMonitoringController::index()` search uses `orWhereRaw("CONCAT(first_name, ' ', last_name) like ?")`, which is MySQL-compatible but not SQLite-compatible (`no such function: CONCAT`). It works in MySQL production; it only errors when the SQLite test DB is queried with a `search` term. No existing test exercised that path. Left unchanged this sprint per the "never rewrite working code" rule; the new test suite verifies filter behavior without the search parameter.

### Design Defense

- PHP bucketing over one-row-per-patient (bounded by patient count) keeps a single portable code path that passes the SQLite test suite and runs in production MySQL, and is well within clinic-scale volumes (32 visits, 2 referrals). SQL-side bucketing would branch by driver for no practical gain at this size.
- Monthly is the primary clinic trend granularity (matches how the clinic reviews workload month to month); a rolling 12-month window shows a full year of trend without growing unbounded. One shared dataset per page keeps every chart and summary reconcilable and removes client-side period state entirely (no selectors, no fetch, no destroy/recreate).
- Analytics scope mirrors the page's KPI counts (all latest assessments, unaffected by `search`/`risk_filter`), so chart totals reconcile with the KPI cards — the same inconsistency the page already documents (KPI counts are filter-free).
- Charts are additive; the existing tables, filters, search, Print, Complete, and navigation render unchanged and are regression-tested.

### Files NOT Modified

- Admin Dashboard, Staff Dashboard, models, migrations, clinical thresholds, decision hierarchy, `.env`, Python files, and the `referrals` table are untouched.

## Sprint 13 — Staff Dashboard Clinic-Wide Statistics (Dashboard Scope Decision)

Status: Complete

### Objective

Make Staff dashboard KPIs and alert counts clinic-wide, exactly matching the Admin dashboard calculations. `assigned_staff_id` remains in the system for patient ownership, assigned-staff display, the My Patients filter, printing/export, and accountability — it is only removed from dashboard statistics.

### Change — `app/Http/Controllers/DashboardController.php`

`staffDashboard()` previously scoped every query with `whereHas('patient', fn($q) => $q->where('assigned_staff_id', $staffId))` (and `Patient::where('assigned_staff_id', $staffId)` for the quick stats). All of that scoping was removed:

- `$staffId` and the `$assignedPatient` closure were deleted.
- **Patients Today / Appointments Today / Pending Checkups** — no longer staff-scoped.
- **HIGH / LOW / ASSESSMENT INCOMPLETE risk counts** (`$staffHighRiskCount`, `$staffLowRiskCount`, `$staffIncompleteCount`) — now identical to Admin's `countLatestByRisk()` (same latest-visit-per-patient subquery, same `risk_level` filter, no assignment constraint).
- **HIGH priority alerts list** (`$highRiskAlerts`) — clinic-wide (still latest-per-patient, `take(5)`).
- **Upcoming Appointments / Follow-up Tasks / Recent Visits** — no longer staff-scoped.
- **Total Patients / Active Patients** — now `Patient::count()` and `Patient::where('status', 'ONGOING')->count()`, matching Admin.
- **Urgent BP / Pending Repeat counts** (`$staffUrgentBpCount`, `$staffPendingRepeatCount`) — clinic-wide, matching Admin.

View variable names were preserved so `resources/views/dashboards/staff.blade.php` renders unchanged. Admin dashboard code was not touched.

### What Was NOT Changed (kept intact)

- `assigned_staff_id` column, migration, `Patient`/`User` relations and `fillable`.
- `PatientController::store()` still assigns `assigned_staff_id = auth()->id()` on creation.
- `PatientController::index()` "My Patients" filter (`?filter=my`) still scopes by the logged-in staff.
- Assigned-staff display on `patients/index.blade.php` and `patients/show.blade.php`.
- Risk Monitoring, Referrals, Patient Records, printing/export — untouched.

### Tests — `tests/Feature/ExplainabilitySprint7Test.php`

- Rewrote "staff dashboard shows only assigned patients" → **"staff dashboard shows clinic-wide counts matching admin"**: a HIGH patient assigned to the logged-in staff and a HIGH patient assigned to another staff now both count; asserts `staff-high-count = 2` AND `admin-high-count = 2`, and both patient names appear in the priority alerts.
- Added **"My Patients filter still shows only the logged-in staff assigned patients"** — verifies the preserved `?filter=my` scoping.
- Added **"patient records still display the assigned staff owner"** — verifies assigned-staff display on the patient profile still works.

### Test Results

- Full suite: **263 passed, 5 failed** (892 assertions). The 5 failures are the documented pre-existing ones (2 `MachineLearningServiceTest` venv/joblib, ExampleTest guest redirect 302, ProfileTest soft-delete, RiskMonitoringStatusTest referral 403). **Zero new regressions.**

### Design Defense

- Dashboard KPI cards, alert lists, and quick stats are operational roll-ups for the clinic, not per-worker task lists; the staff's personal queue remains available via the Patients page "My Patients" tab, which still uses `assigned_staff_id`. This keeps ownership data authoritative without distorting clinic-wide statistics.
- Staff and Admin now share one query path (`countLatestByRisk()` / `latestVisitSubquery()`), removing the previous fork where the same concept (e.g., HIGH risk) displayed two different numbers depending on role.

### Files NOT Modified

- Risk Monitoring, Referrals, Patient Records, models, migrations, clinical thresholds, `.env`, Python files.

## Next Planned Work

1. MAT-WARN evaluation and referral integration remains **deferred and requires clinical approval** (symptom → action-level mapping is NOT implemented; Sprint 11 deliberately confirmed the fields are record-only). This is a decision for a future, separately approved sprint.
2. Resolve the machine-specific ML environment: create/point `PYTHON_PATH` to a Python environment with `joblib`, `pandas`, and `scikit-learn` so the two `MachineLearningServiceTest` integration tests pass and the live ML path works.
3. Keep EDD outcome monitoring for a later dedicated sprint.