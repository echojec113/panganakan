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

## Next Planned Work

1. Introduce structured assessment result objects
2. Implement EDD-triggered pregnancy outcome monitoring