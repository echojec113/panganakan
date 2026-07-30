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

## Next Planned Work

1. Execute migration `2026_08_01_000001_add_bp_verification_to_prenatal_visits.php` after manual inspection.
2. Begin Sprint 11: Warning-symptom evaluation (MAT-WARN) and referral integration.
3. Keep EDD outcome monitoring for a later dedicated sprint.