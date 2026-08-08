# Clinical Factor Matrix

**Document-to-Code Traceability for the Maternity CDSS**

| Field | Value |
|-------|-------|
| Version | 1.0 |
| Date | 30 July 2026 |
| Status | DRAFT — Pending clinical review |
| Purpose | Bridge between source documents 0–7, current Laravel implementation, and proposed future clinical logic |
| Scope | Prenatal risk assessment in a Philippine lying-in clinic (midwife-led, no on-site obstetrician) |

---

## Section 1 — System Decision Hierarchy

### Current Hierarchy (as implemented)

```
0. BP-URG IMMEDIATE URGENT SAFETY EVALUATION (Sprint 10)
   BloodPressureAssessmentService::assess()
   └── BP >= 160/110 → HIGH + URGENT_CLINICAL_REVIEW + missing_records preserved
   └── Pre-completeness; preserves missing-record display alongside BP-URG outcome

1. REQUIRED-RECORD COMPLETENESS CHECK
   CompletenessValidator::missingRequiredRecords()
   └── Medical History, Ultrasound Record, Birth Plan
   └── Missing any → ASSESSMENT INCOMPLETE (COMPLETENESS)
   └── BP-H alert preserved if triggered

2. DETERMINISTIC CLINICAL-RULE EVALUATION
   ClinicalRuleEngine::evaluate()
   └── 7 rule groups (BP removed to BloodPressureAssessmentService)
   └── BP-H evaluated in RiskAssessmentService post-completeness
   └── Any triggered → HIGH (RULE_BASED)

3. MACHINE-LEARNING EXECUTION (only when eligible)
   MachineLearningService::predict()
   └── Valid HIGH → HIGH (MACHINE_LEARNING)
   └── Valid LOW → LOW (MACHINE_LEARNING)
   └── Invalid → ASSESSMENT INCOMPLETE (MACHINE_LEARNING_INVALID)

4. FINAL DECISION INTEGRATION
   DecisionIntegrationService::decide()
   └── 5-path hierarchy (see below)

5. RESULT PERSISTENCE
   PrenatalVisitController → $visit->update([...toArray() fields])

6. EXPLAINABILITY DISPLAY
   ├── Patient profile (right sidebar Risk Assessment Card)
   ├── Dashboard (summary cards + priority lists)
   ├── Risk Monitoring (filterable table with per-row evidence)
   └── Printable clinical report

7. HUMAN STAFF REVIEW AND ACTION
   └── Staff reviews HIGH → referral, closer follow-up, or review
   └── Staff reviews LOW → continues routine care
   └── Staff reviews INCOMPLETE → completes missing records
```

### Current Decision-Integration Paths

| Priority | Condition | Result | Decision Source | Next Visit |
|----------|-----------|--------|----------------|------------|
| 1 | Missing records | ASSESSMENT INCOMPLETE | COMPLETENESS | +30 days |
| 2 | Rule reasons present | HIGH | RULE_BASED | +3 days |
| 3 | Valid ML HIGH | HIGH | MACHINE_LEARNING | +3 days |
| 4 | Valid ML LOW | LOW | MACHINE_LEARNING | +30 days |
| 5 | Fallback (invalid ML / no rule) | ASSESSMENT INCOMPLETE | MACHINE_LEARNING_INVALID | +30 days |

### Current Override Rules

- Missing required records → prevents LOW classification
- Deterministic HIGH rules → override ML (any valid ML result)
- ML invalid/unavailable → does NOT become LOW
- No MODERATE or VERY HIGH category exists
- The system supports clinical judgment and does not diagnose

### Document-Proposed Hierarchy (DOCU 4 Part 12 Section 5)

```
1. Emergency/urgent trigger   → HIGH + urgent action
2. Deterministic HIGH          → HIGH (rules override ML)
3. ML HIGH (no rule)           → HIGH (ML-only)
4. Incomplete (no HIGH)        → ASSESSMENT INCOMPLETE
5. LOW (complete + valid ML)   → LOW
```

**Difference from current**: Documents add an explicit `Emergency/urgent` priority above deterministic HIGH. Sprint 10 implemented BP-URG as a pre-completeness urgent safety evaluation with `urgency` output metadata (URGENT_CLINICAL_REVIEW) and a separate `bp_assessment` JSON column. The system now matches the document-proposed hierarchy for BP.

### Recommended Future Behavior

- Urgency is now implemented as a separate metadata field (urgency column + bp_assessment JSON) ✅
- BP-URG pre-completeness bypass implemented ✅
- Emergency override engine path added (BP-URG in RiskAssessmentService before completeness) ✅
- Preserve existing hierarchy otherwise; it matches document specification

---

## Section 2 — Clinical Factor Matrix

---

### FACTOR ID: AGE-Y

**Factor name:** Adolescent pregnancy (age below 19)

**Clinical domain:** Maternal demographics

**Document sources:**
- Document 1, Part 1 (maternal agedocument1.docx) — Maternal Age evidence synthesis
- Document 4, Part 1 — Rewritten Maternal Age, Rules AGE-Y
- Document 4, Part 12 — Final Deterministic Rule Register: `AGE-Y Age <19 years → HIGH`
- Document 7, Part 3 — Rule-Based Explainability Specification

**Source confidence:** PRIMARY-GUIDELINE CITED (WHO, ACOG)

**Current database fields:**
- `patients.age` (integer, nullable in migration, required in form)

**Current model fields/casts:**
- `Patient::$fillable` includes `age`
- No cast — stored as integer

**Current input form:**
- `resources/views/patients/create.blade.php` — age field with auto-calculation from birthdate
- `resources/views/patients/edit.blade.php` — same

**Current controller validation:**
- `PatientController` validates `age` as `required|integer|min:10|max:60`
- Age is auto-calculated from birthdate via JavaScript in forms

**Current ClinicalRuleEngine behavior:**
```php
if ($patient->age < 19) {
    $reasons[] = "Teenage pregnancy (under 19)";
}
```
- Executes BEFORE the `elseif` for age >=35 (mutually exclusive branch)

**Current ML usage:**
- Feature 1 of 12 in `MachineLearningService::buildFeatureArray()`: `(float)($patient->age ?? 0)`
- Used by Random Forest model

**Current decision effect:** HIGH (when triggered, overrides ML)

**Current explainability:**
- Reason string: "Teenage pregnancy (under 19)"
- Displayed in Risk Assessment Card under "Triggered Rules" / "Risk Factors"
- Decision source: Clinical Rules (orange)

**Current test coverage:**
- `ClinicalRuleEngineTest`: "age 18 returns teenage pregnancy reason"
- `DecisionIntegrationServiceTest`: rule reasons return HIGH path

**Current implementation status:** IMPLEMENTED AND ACCEPTABLE

**Proposed exact future rule:**
```
IF patient.age < 19 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = AGE-Y
    urgency = REVIEW_REQUIRED
    ML execution = SKIPPED
```
Same as current; documents confirm the threshold as appropriate.

**Boundary cases:**
- Age 18 → HIGH
- Age 19 → no trigger (falls through to age >=35 check)
- Age 10 (minimum validated) → HIGH

**Gestational context:** None — age is independent of gestational age

**Evidence/confirmation requirements:** Patient-reported or document-verified age; birthdate preferred over stated age

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** REVIEW REQUIRED

**ML behavior:** Skipped after deterministic HIGH

**Clinical interpretation wording:** "The patient's age indicates a need for age-sensitive clinical and social assessment."

**Suggested verification wording:** "Verify patient's age from birthdate or valid identification."

**Suggested clinical action wording:** "Provide age-appropriate antenatal care and social support; refer for adolescent-focused services if available."

**Patient-profile UI:**
- Badge: High (red)
- Evidence: "Teenage pregnancy (under 19)" in Risk Factors list

**Risk Monitoring UI:**
- Decision source: Clinical Rules (orange)
- Evidence: "Teenage pregnancy (under 19)"

**Dashboard UI:** Counted in HIGH patient total; detail includes reason

**Printable report:**
- Decision Source: Clinical Rules
- Triggered Rule: AGE-Y — Teenage pregnancy (under 19)
- Clinical interpretation text

**Referral integration:** Supports referral evaluation but does not mandate immediate referral

**Required automated tests:**
- Age 10–18 → HIGH via AGE-Y
- Age 19 → no AGE-Y trigger
- Age 18 + other factors → HIGH with multiple reasons preserved
- Age 18 + complete records + ML LOW → HIGH (rule override confirmed)

**Clinical approval needed:** NO — currently approved and implemented

**Open questions:** None

---

### FACTOR ID: AGE-A

**Factor name:** Advanced maternal age (35+) with first pregnancy

**Clinical domain:** Maternal demographics

**Document sources:**
- Document 1, Part 1 — Maternal Age evidence
- Document 4, Part 1 — Rewritten Maternal Age, Rule AGE-A
- Document 4, Part 12 — Final Deterministic Rule Register: `AGE-A Age >=35 years and first pregnancy → HIGH`
- Document 7, Part 3 — Explainability specification

**Source confidence:** PRIMARY-GUIDELINE CITED (ACOG, with project-specific narrow interpretation)

**Current database fields:**
- `patients.age` (integer)
- `patients.gravida` (integer, nullable)
- `patients.para` (integer, nullable)

**Current model fields/casts:** Same as AGE-Y; gravida and para are stored as plain integers

**Current input form:**
- Patient create/edit includes gravida and para fields with JS validation (`gravida` must be >= `para`)

**Current controller validation:**
- `gravida` and `para`: `nullable|integer|min:0|max:20`

**Current ClinicalRuleEngine behavior:**
```php
elseif ($patient->age >= 35 && $patient->gravida == 1 && $patient->para == 0) {
    $reasons[] = "Advanced maternal age (35+) and first pregnancy";
}
```
- Uses `elseif` — only evaluated when age >= 19 (no AGE-Y trigger)
- Requires gravida == 1 AND para == 0 (first pregnancy)

**Current ML usage:**
- Feature 2 (gravida) and Feature 3 (para) in `buildFeatureArray()`
- Age is Feature 1

**Current decision effect:** HIGH (when triggered, overrides ML)

**Current explainability:**
- Reason string: "Advanced maternal age (35+) and first pregnancy"
- Same display path as AGE-Y

**Current test coverage:**
- `ClinicalRuleEngineTest`: "age 35 gravida 1 para 0 returns advanced maternal age reason"
- Implicit: age 35 + gravida > 1 does NOT trigger; age 34 + first pregnancy does NOT trigger

**Current implementation status:** IMPLEMENTED AND ACCEPTABLE

**Proposed exact future rule:**
```
IF patient.age >= 35 AND patient.gravida == 1 AND patient.para == 0 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = AGE-A
    urgency = REVIEW_REQUIRED
    ML execution = SKIPPED
```

**Boundary cases:**
- Age 35, G1P0 → HIGH
- Age 35, G2P1 → no trigger (documents note this is narrower than ACOG general counseling threshold)
- Age 40, G1P0 → HIGH via AGE-A (not a separate rule in current code)
- Age 34, G1P0 → no trigger

**Gestational context:** None

**Evidence/confirmation requirements:** Patient-reported obstetric history; gravida and para verified during registration

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** REVIEW REQUIRED

**ML behavior:** Skipped after deterministic HIGH

**Clinical interpretation wording:** "The patient's age and first-pregnancy status indicate a need for individualized obstetric review."

**Suggested verification wording:** "Verify age from birthdate; confirm gravida and para from history."

**Patient-profile UI:**
- Badge: High (red)
- Evidence: "Advanced maternal age (35+) and first pregnancy"

**Required automated tests:**
- Age 35, G1P0 → HIGH via AGE-A
- Age 35, G2P1 → no AGE-A trigger
- Age 40, G1P0 → HIGH via AGE-A
- Age 40, G2P2 → no AGE-A trigger
- Age 35, G1P0 + ML LOW → HIGH (rule override confirmed)

**Clinical approval needed:** NO — currently approved and implemented

**Open questions:** Documents note this is narrower than ACOG guidelines. A separate rule for age >=40 regardless of parity was discussed but not implemented. Require clinical approval before adding `AGE-40` rule.

---

### FACTOR ID: BP-H

**Factor name:** Elevated blood pressure (hypertension threshold)

**Clinical domain:** Maternal vital signs

**Document sources:**
- Document 1, Part 2 — Blood Pressure and Hypertensive Disorders
- Document 4, Part 2 — Rewritten Hypertension, Rule BP-H
- Document 4, Part 12 — Final Register: `BP-H Systolic >=140 OR diastolic >=90 mmHg → HIGH`
- Document 5, Part 2 — Risk Categories
- Document 7, Part 3 — Rule-Based Explainability Specification

**Source confidence:** PRIMARY-GUIDELINE CITED (NICE NG133, WHO, ISSHP)

**Current database fields:**
- `prenatal_visits.bp_sys` (integer/nullable in migration)
- `prenatal_visits.bp_dia` (integer/nullable in migration)

**Current model fields/casts:** None — stored as raw integers

**Current input form:**
- `resources/views/prenatal_visits/create.blade.php` — systolic (60–200) and diastolic (40–130) with JS validation (sys > dia)
- `resources/views/prenatal_visits/edit.blade.php` — same

**Current controller validation:**
- `bp_sys`: `required|numeric|min:60|max:200`
- `bp_dia`: `required|numeric|min:40|max:130`
- Additional: `if ($request->bp_sys <= $request->bp_dia) → validation error`

**Current ClinicalRuleEngine behavior:** REMOVED (Sprint 10) — BP logic moved to `BloodPressureAssessmentService::assess()`

**Current BloodPressureAssessmentService behavior:**
```php
// Assesses all BP inputs (initial + repeat) and returns:
// - triggered: bool
// - reason_code: 'BP-H' | 'BP-URG' | null
// - risk_level: 'HIGH' | null
// - urgency: 'PROMPT' | 'URGENT_CLINICAL_REVIEW' | null
// - verification_status: one of 4 enum values
// - threshold, label, interpretation, action: string
```

**Current ML usage:**
- Feature 4 (bp_sys) and Feature 5 (bp_dia) in `buildFeatureArray()`
- Used by Random Forest

**Current decision effect:**
- BP-H (>=140/90): HIGH via RiskAssessmentService post-completeness
- Urgency: PROMPT
- Verification: PENDING_REPEAT suggested if no repeat recorded
- Does NOT bypass completeness check

**Current explainability:**
- Reason string: "Hypertension (BP: X/Y)" — set by RiskAssessmentService
- BP Assessment section shows classification, interpretation, action
- Urgency badge (PROMPT = yellow) displayed in patient profile
- Structured bp_assessment JSON stored for audit

**Current test coverage:**
- `BloodPressureAssessmentServiceTest`: "bp 140 over 90 returns BP-H triggered with PROMPT urgency"
- `RiskAssessmentService` tests via `DecisionIntegrationServiceTest` integration

**Current implementation status:** REFINED (Sprint 10) — BP logic extracted from ClinicalRuleEngine; verification and urgency implemented

**Proposed exact future rule:**
```
IF bp_sys >= 140 OR bp_dia >= 90 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = BP-H
    urgency = PROMPT (qualifies as "prompt qualified BP assessment")
    ML execution = SKIPPED
```

**Boundary cases:**
- 139/89 → no trigger
- 140/80 → HIGH (systolic triggers)
- 120/90 → HIGH (diastolic triggers)
- 140/90 → HIGH

**Gestational context:** None for threshold — but document notes gestational timing affects interpretation (pre-existing vs. gestational vs. pre-eclamptic)

**Evidence/confirmation requirements:** Documented single-visit measurement. Document recommends repeat measurement confirmation but this is not currently implemented.

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** PROMPT

**ML behavior:** Skipped after deterministic HIGH

**Clinical interpretation wording:** "The recorded blood pressure reading is at or above the threshold that requires prompt qualified assessment."

**Suggested verification wording:** "Confirm measurement technique and consider repeat measurement according to clinic protocol. Verify cuff size, patient position, and rest period."

**Suggested clinical action wording:** "Schedule prompt qualified BP assessment and review. Refer for further evaluation if repeat readings remain elevated."

**Patient-profile UI:**
- Badge: High (red)
- Evidence: "Hypertension (BP: X/Y)" with exact values

**Risk Monitoring UI:** Evidence summary includes BP reason with values

**Required automated tests:**
- BP 140/80 → HIGH via BP-H (not BP-URG)
- BP 120/90 → HIGH via BP-H
- BP 139/89 → no BP trigger (but may still be ML-evaluated)
- BP 140/90 + ML LOW → HIGH (rule override confirmed)

**Current code gap (resolved Sprint 10):** BP-URG is now evaluated before completeness as an urgent safety net; BP-H remains in post-completeness evaluation. Repeat-measurement workflow is implemented. Missing records preserved alongside BP-URG/BP-H outcomes.

**Clinical approval needed:** YES — for BP-URG pre-completeness bypass, BP verification workflow, and urgency display design. BP-H remains standard deterministic HIGH without completeness bypass (approved design).

**Open questions:**
- Should urine protein test results be integrated?
- Staff training materials needed for BP verification workflow

---

### FACTOR ID: BP-URG

**Factor name:** Severely elevated blood pressure (urgent threshold)

**Clinical domain:** Maternal vital signs

**Document sources:**
- Document 1, Part 2 — Hypertensive Disorders
- Document 4, Part 2 — Rewritten Hypertension, Rule BP-URG
- Document 4, Part 12 — `BP-URG Systolic >=160 OR diastolic >=110 → HIGH + urgent`
- Document 5, Part 7 — Clinical Risk Escalation Framework

**Source confidence:** PRIMARY-GUIDELINE CITED (NICE NG133, WHO)

**Current database fields:** Same as BP-H (`bp_sys`, `bp_dia`)

**Current model fields/casts:** Same as BP-H

**Current input form:** Same as BP-H

**Current controller validation:** Same as BP-H

**Current ClinicalRuleEngine behavior:** REMOVED (Sprint 10) — BP logic moved to `BloodPressureAssessmentService::assess()`

**Current BloodPressureAssessmentService behavior:**
```php
// Triggered when bp_sys >= 160 OR bp_dia >= 110
// Returns: triggered=true, reason_code='BP-URG', risk_level='HIGH',
//          urgency='URGENT_CLINICAL_REVIEW', action='Immediate repeat measurement...'
// Evaluated in RiskAssessmentService BEFORE completeness check
// Missing records preserved alongside BP-URG outcome
// Initial BP severe → verification_status = PENDING_REPEAT
```

**Current ML usage:** Same feature positions as BP-H

**Current decision effect:**
- HIGH + URGENT_CLINICAL_REVIEW (pre-completeness urgent safety override)
- Urgency: URGENT_CLINICAL_REVIEW
- Missing records preserved (not hidden by bypass)
- Repeat BP resolution: if repeat measurements are normal, triggered=false

**Current explainability:**
- Red URGENT badge in Risk Assessment Card
- BP Assessment section with classification, interpretation, action text
- Decision source: Clinical Rules (orange) with urgency indicator
- Structured bp_assessment JSON stored for audit

**Current test coverage:**
- `BloodPressureAssessmentServiceTest`: severe BP triggers BP-URG with URGENT_CLINICAL_REVIEW
- `BloodPressureAssessmentServiceTest`: BP-URG repeat-resolved returns not triggered
- `DecisionIntegrationServiceTest`: completeness path accepts urgency and bp_assessment for BP-URG bypass

**Current implementation status:** REFINED (Sprint 10) — urgency, pre-completeness bypass, repeat workflow, and structured explainability implemented

**The gap (resolved Sprint 10):** Urgency is now distinguished. Documents require:
- Emergency override priority before completeness check ✅
- Urgency metadata (URGENT_CLINICAL_REVIEW) ✅
- Different recommendation (immediate assessment / hospital transfer) ✅
- Different explainability display ✅

**Proposed exact future rule:** (IMPLEMENTED Sprint 10)
```
IF bp_sys >= 160 OR bp_dia >= 110 THEN
    risk_level = HIGH
    urgency = URGENT_CLINICAL_REVIEW
    decision_source = RULE_BASED
    reason_code = BP-URG
    ML execution = SKIPPED
    action = "Immediate repeat measurement and qualified clinical assessment.
              Emergency referral or transfer according to local protocol."
```
Additionally: this is evaluated BEFORE the completeness check ✅.

**Boundary cases:**
- 160/100 → URGENT_CLINICAL_REVIEW (systolic triggers)
- 150/110 → URGENT_CLINICAL_REVIEW (diastolic triggers)
- 159/109 → HIGH (BP-H only) via current thresholds
- 160/110 → URGENT_CLINICAL_REVIEW + HIGH

**Gestational context:** Gestational timing matters (early-onset vs late-onset) but is not currently captured

**Evidence/confirmation requirements:** Current code uses single measurement. Document strongly recommends repeat measurement confirmation.

**Proposed risk classification:** HIGH (deterministic, with urgency override)

**Proposed urgency:** URGENT

**ML behavior:** Skipped (should be evaluated before completeness check)

**Clinical interpretation wording:** "The recorded blood pressure reading is in the severe range and requires immediate repeat measurement and urgent clinical assessment."

**Suggested verification wording:** "Repeat BP measurement after rest. Verify cuff size, position, and technique. Assess for symptoms (severe headache, visual disturbance, chest pain, shortness of breath)."

**Suggested clinical action wording:** "Arrange immediate or same-day qualified assessment. Ensure transport, receiving facility, and handover. Do not delay for routine software completion."

**Patient-profile UI:**
- Badge: High (red) with urgent indicator
- Evidence: "Severe hypertension (BP: X/Y)" — visually distinguished

**Risk Monitoring UI:** Decision source "Clinical Rules — Urgent"

**Dashboard UI:** Should appear in HIGH count with urgent flag; potentially a separate urgent counter

**Printable report:** Separate urgent-clinical-action section

**Referral integration:** Should recommend urgent referral evaluation

**Required automated tests:**
- BP 160/100 → HIGH + urgent flag
- BP 120/110 → HIGH + urgent flag
- BP 160/110 + missing records → HIGH + urgent (bypass completeness)
- BP 159/109 → HIGH (BP-H only) no urgent flag
- Urgency metadata present and correctly populated

**Clinical approval needed:** YES — for BP-URG pre-completeness bypass and urgency metadata implementation (design complete, awaiting clinical reviewer sign-off)

**Open questions (resolved Sprint 10):**
- Urgency display: URGENT_CLINICAL_REVIEW badge (red animate-pulse) in patient profile ✅
- Repeat-measurement workflow: both-or-neither validation, verification statuses, BP_INITIAL_EDITED clears repeat pair ✅
- Audit logging for BP actions implemented ✅

---

### FACTOR ID: DM-01

**Factor name:** Diabetes in pregnancy

**Clinical domain:** Medical history / current condition

**Document sources:**
- Document 1, Part 3 — Diabetes in Pregnancy
- Document 4, Part 3 — Rewritten Diabetes, Rule DM-01
- Document 4, Part 12 — `DM-01 Diabetes recorded present → HIGH`

**Source confidence:** PRIMARY-GUIDELINE CITED (WHO, NICE, ACOG)

**Current database fields:**
- `prenatal_visits.diabetes` (boolean, default false) — checkbox in visit form
- `medical_histories.diabetes` (boolean, nullable) — checkbox in medical history form

**Current model fields/casts:**
- `PrenatalVisit`: `'diabetes' => 'boolean'`
- `MedicalHistory`: stored as plain boolean (added in migration 2026_07_12_100001)

**Current input form:**
- `resources/views/prenatal_visits/create.blade.php` — "Diabetes" checkbox under Risk Factors
- `resources/views/medical_histories/create.blade.php` — "Diabetes" checkbox in condition grid

**Current controller validation:**
- `diabetes`: `required|boolean` (prenatal visit)
- No validation on medical history (checkboxes default to unchecked)

**Current ClinicalRuleEngine behavior:**
```php
if ($inputs['diabetes'] == 1) {
    $reasons[] = "Diabetes";
}
```
- Uses `$inputs['diabetes']` (from prenatal visit form), not `$patient->medicalHistory->diabetes`

**Current ML usage:**
- Feature 9 of 12: `(int)($inputs['diabetes'] ?? 0)`
- Binary feature (0 or 1)

**Current decision effect:** HIGH

**Current explainability:**
- Reason string: "Diabetes"
- Documents recommend enhanced explanation showing it as requiring medical/obstetric co-management

**Current test coverage:**
- `ClinicalRuleEngineTest`: "diabetes returns diabetes reason"
- Single test case only

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. Source of truth ambiguity: currently reads from prenatal visit checkbox, not from medical history. The medical history also has a diabetes field. These could conflict. **Sprint 11 decision: the prenatal-visit checkbox remains the engine's source of truth; Medical History's `diabetes`/`anemia` are stored/displayed but not consumed by the engine.** The CDSS allowlist and the exact data path are locked by tests.
2. No distinction between pre-existing T1DM/T2DM and GDM — documents recognize this as a limitation
3. No treatment-plan or glycemic-control information used
4. Reason string "Diabetes" is minimal — document proposes more contextual wording
5. No provenance tracking (when diagnosed, by whom, type)

**Proposed exact future rule:**
```
IF (prenatal_visit.diabetes == 1 OR medical_history.diabetes == 1) THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = DM-01
    urgency = PROMPT (for co-management planning)
    ML execution = SKIPPED
```
Documents also propose DM-INC (incomplete if diabetes flagged but no treatment detail). Not currently implemented.

**Boundary cases:**
- Both sources true → HIGH (single reason, no duplicate)
- One true, one false/null → HIGH
- Both false/null → no trigger

**Gestational context:** Gestational age at diagnosis is clinically important but not captured

**Evidence/confirmation requirements:** Documents emphasize that diabetes should be a confirmed diagnosis, not just a checkbox. Current implementation uses a checkbox without diagnostic confirmation.

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** PROMPT

**ML behavior:** Skipped after deterministic HIGH

**Clinical interpretation wording:** "A recorded history or current finding of diabetes indicates a need for medical and obstetric co-management."

**Suggested verification wording:** "Verify diabetes type (pregestational vs. gestational), diagnostic method, treatment status, and recent glycemic control."

**Patient-profile UI:**
- Evidence: "Diabetes — requires medical and obstetric co-management"
- Should show source (prenatal visit vs medical history)

**Required automated tests:**
- Diabetes in prenatal visit → HIGH via DM-01
- Diabetes in medical history only → HIGH via DM-01 (requires updating rule engine to check medical history — NOT implemented; deferred for clinical approval)
- Both sources → single DM-01 reason, not duplicated
- Diabetes + ML LOW → HIGH (rule override)
- Warning-symptom inputs → never produce reasons (Sprint 11 allowlist test)

**Clinical approval needed:** YES — for:
- Adding medical history as a diabetes source
- Clarifying source-of-truth reconciliation
- Proposing DM-INC (incomplete without treatment detail)

**Open questions:**
- Should the rule engine read from medical_history or only prenatal_visit?
- Should conflicting values between sources trigger an inconsistency flag?
- Should HbA1c or blood glucose fields be added to support severity assessment?

---

### FACTOR ID: AN-01

**Factor name:** Maternal anemia

**Clinical domain:** Medical history / current condition

**Document sources:**
- Document 1, Part 4 — Anemia in Pregnancy
- Document 4, Part 4 — Rewritten Maternal Anemia, Rule AN-01
- Document 4, Part 12 — `AN-01 Anemia recorded present → HIGH`

**Source confidence:** PRIMARY-GUIDELINE CITED (WHO 2024 trimester-specific Hb cutoffs)

**Current database fields:**
- `prenatal_visits.anemia` (boolean, default false) — checkbox in visit form
- `medical_histories.anemia` (boolean, nullable) — checkbox

**Current model fields/casts:** Same pattern as diabetes

**Current input form:** Same pattern as diabetes — checkbox in both prenatal visit and medical history

**Current controller validation:** `anemia: required|boolean` (prenatal visit)

**Current ClinicalRuleEngine behavior:**
```php
if ($inputs['anemia'] == 1) {
    $reasons[] = "Anemia";
}
```

**Current ML usage:**
- Feature 12 of 12: `(int)($inputs['anemia'] ?? 0)`

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "anemia returns anemia reason"
- Single test case

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. Boolean checkbox is insufficient — documents emphasize that anemia requires laboratory Hb values for severity grading
2. WHO trimester-specific thresholds cannot be applied without Hb value and gestational age
3. Source-of-truth ambiguity (same as diabetes — visit vs. history)
4. Reason string "Anemia" is minimal

**Proposed exact future rule:**
```
IF (prenatal_visit.anemia == 1 OR medical_history.anemia == 1) THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = AN-01
    urgency = PROMPT
    ML execution = SKIPPED
    limitation = "Severity, cause, and treatment cannot be determined from available data"
```

**Boundary cases:** Same pattern as diabetes (boolean trigger)

**Gestational context:** WHO Hb thresholds are trimester-specific. Cannot be applied without Hb value and gestational age.

**Evidence/confirmation requirements:** Documents strongly recommend laboratory Hb value. Current implementation uses checkbox only.

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** PROMPT

**Clinical interpretation wording:** "A recorded history or current finding of anemia requires verification of severity, cause, treatment, and birth-plan consideration."

**Suggested verification wording:** "Obtain complete blood count. Compare haemoglobin against trimester-specific thresholds. Identify cause and treatment status."

**Required automated tests:**
- Anemia in prenatal visit → HIGH via AN-01
- Anemia in medical history → HIGH (after engine update)
- Both medical history and prenatal visit → single reason
- Anemia + ML LOW → HIGH (rule override)

**Clinical approval needed:** YES — for:
- Adding laboratory Hb field (requires new migration)
- Trimester-specific threshold logic (requires clinical approval)
- Source reconciliation with medical history

**Open questions:**
- Should AN-INC (incomplete without Hb) be implemented?
- Should Hb and hematocrit fields be added to prenatal visit or as a new lab table?
- How to handle trimester-specific thresholds in the rule engine

---

### FACTOR ID: CS-01

**Factor name:** Previous cesarean delivery

**Clinical domain:** Obstetric history

**Document sources:**
- Document 1, Part 5 — Previous Cesarean Birth
- Document 4, Part 5 — Rewritten Previous Cesarean, Rule CS-01
- Document 4, Part 12 — `CS-01 One or more previous cesarean deliveries → HIGH`

**Source confidence:** PRIMARY-GUIDELINE CITED (NICE NG192, RCOG)

**Current database fields:**
- `patients.previous_cs` (integer/boolean — **missing from migration** but present in `$fillable`)
- Present in production database only

**Current model fields/casts:** `Patient::$fillable` includes `previous_cs`; no cast

**Current input form:**
- Patient create/edit includes "Previous CS" field (as a yes/no)

**Current controller validation:** Not explicitly validated in PatientController store/update (nullable)

**Current ClinicalRuleEngine behavior:**
```php
if ($patient->previous_cs == 1) {
    $reasons[] = "Previous cesarean section";
}
```

**Current ML usage:**
- Feature 10 of 12: `(int)($patient->previous_cs ?? 0)`

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "previous cs returns previous cesarean reason"
- Single test case

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. **CRITICAL**: `previous_cs` column is in `$fillable` but missing from ALL migrations — causes test failures on fresh SQLite
2. Reason string uses "cesarean section" — document recommends "previous cesarean delivery" (minor wording)
3. No scar type, number of previous cesareans, or interpregnancy interval captured
4. Documents clarify that previous CS does not automatically mandate repeat cesarean — this distinction is not in the reason text

**Proposed exact future rule:**
```
IF patient.previous_cs == 1 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = CS-01
    urgency = REVIEW_REQUIRED
    ML execution = SKIPPED
```

**Boundary cases:**
- previous_cs = 1 → HIGH
- previous_cs = 0 → no trigger
- previous_cs = null → no trigger

**Gestational context:** None for basic rule

**Evidence/confirmation requirements:** Patient-reported or record-verified history

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** REVIEW_REQUIRED

**Clinical interpretation wording:** "A history of cesarean delivery requires hospital-level obstetric birth planning. This does not automatically mean another cesarean."

**Suggested verification wording:** "Verify number of previous cesareans, uterine scar type if known, and indication for previous operation."

**Patient-profile UI:**
- Evidence: "Previous cesarean delivery — requires hospital birth planning"
- The "not automatically another cesarean" clarification should be visible

**Required automated tests:**
- previous_cs = 1 → HIGH via CS-01
- previous_cs = 0 → no CS-01 trigger
- previous_cs = null → no CS-01 trigger
- previous_cs + ML LOW → HIGH (rule override)
- Test passes on fresh SQLite (requires migration fix)

**Clinical approval needed:** NO for rule itself; YES for migration fix

**Open questions:**
- Number of previous cesareans: should >1 change urgency?
- Scar type (unknown / lower transverse / classical) — should this influence risk?
- Interpregnancy interval < 18 months after CS — should this be a separate factor?

---

### FACTOR ID: RM-03

**Factor name:** Recurrent miscarriage (3 or more)

**Clinical domain:** Obstetric history

**Document sources:**
- Document 1, Part 6 — Miscarriage and Adverse Obstetric History
- Document 4, Part 6 — Rewritten Recurrent Miscarriage, Rule RM-03
- Document 4, Part 12 — `RM-03 Miscarriage count >=3 → HIGH`

**Source confidence:** DOCUMENT-SUPPORTED — SOURCE VERIFICATION REQUIRED (RCOG uses >=3; ACOG/ESHRE/ASRM use >=2)

**Current database fields:**
- `patients.miscarriage` (integer — **missing from migration** but in `$fillable`)

**Current model fields/casts:** `Patient::$fillable` includes `miscarriage`; no cast

**Current input form:** Patient create/edit — miscarriage count field

**Current controller validation:** `nullable|integer|min:0|max:20`

**Current ClinicalRuleEngine behavior:**
```php
if ($patient->miscarriage >= 3) {
    $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
}
```

**Current ML usage:**
- Feature 11 of 12: `(int)($patient->miscarriage ?? 0)`

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "miscarriage 3 returns miscarriage reason"
- Boundary: miscarriage = 2 does not trigger (test confirms >=3)

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. **CRITICAL**: `miscarriage` column missing from migrations
2. Documents acknowledge >=3 is conservative (aligned with RCOG); ACOG/ESHRE use >=2
3. No distinction between first-trimester and later losses
4. No current-symptoms context (bleeding in current pregnancy would escalate urgency)

**Proposed exact future rule:**
```
IF patient.miscarriage >= 3 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = RM-03
    urgency = REVIEW_REQUIRED
    ML execution = SKIPPED
```

**Boundary cases:**
- Miscarriage = 3 → HIGH via RM-03
- Miscarriage = 2 → no RM-03 (document acknowledges >=2 guidelines exist)
- Miscarriage = 0 → no trigger
- Miscarriage = null → no trigger

**Gestational context:** Timing of losses is clinically relevant but not captured

**Evidence/confirmation requirements:** Patient-reported history

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** REVIEW_REQUIRED

**Clinical interpretation wording:** "A history of three or more previous losses indicates a need for specialist assessment and supportive antenatal care."

**Suggested verification wording:** "Verify number, trimester, and documented cause of previous losses. Current pregnancy symptoms should be assessed separately."

**Patient-profile UI:**
- Evidence: "History of N miscarriage(s) — requires specialist assessment"
- Should include note that >=2 may also be clinically significant per other guidelines

**Required automated tests:**
- miscarriage = 3 → HIGH via RM-03
- miscarriage = 2 → no RM-03 trigger (current behavior)
- miscarriage = 0 → no trigger
- miscarriage = null → no trigger
- miscarriage >= 3 + ML LOW → HIGH (rule override)
- Test passes on fresh SQLite

**Clinical approval needed:** YES — for migration fix and for deciding whether >=2 should be considered

**Open questions:**
- Should >=2 trigger a lower-urgency rule or a clinical advisory (not HIGH)?
- How to handle current-pregnancy bleeding as a separate factor
- Should miscarriage count be capped at a maximum in display (e.g., "3+" instead of exact count)?

---

### FACTOR ID: US-P01

**Factor name:** Abnormal fetal presentation

**Clinical domain:** Ultrasound / fetal assessment

**Document sources:**
- Document 1, Part 10 — Fetal Presentation, Amniotic Fluid, Placenta
- Document 4, Part 7 — Rewritten Ultrasound Findings, Rule US-P01
- Document 4, Part 12 — `US-P01 Breech, transverse, or oblique presentation → HIGH`

**Source confidence:** PRIMARY-GUIDELINE CITED (ISUOG, NICE, ACOG)

**Current database fields:**
- `ultrasounds.presentation` (string, nullable)

**Current model fields/casts:** No cast — stored as string

**Current input form:**
- `resources/views/ultrasounds/create.blade.php` — "Presentation" text/select field

**Current controller validation:** `nullable|string|max:255`

**Current ClinicalRuleEngine behavior:**
```php
$presentation = strtoupper(trim((string) $ultrasound->presentation));
if (in_array($presentation, ['BREECH', 'TRANSVERSE', 'OBLIQUE'], true)) {
    $reasons[] = "Abnormal fetal presentation ({$presentation})";
}
```

**Current ML usage:** Not a direct feature; only presentation's indirect effects are captured through other US features

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "breech presentation returns abnormal presentation reason"
- Only one presentation variant explicitly tested

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. **Gestational-age context**: Presentation is only meaningful near term. Current code does not consider gestational age at scan. A breech at 20 weeks is normal.
2. Free-text presentation field allows any value — normalization is done via `strtoupper(trim())` but only 3 values are recognized
3. No structured dropdown for presentation in the ultrasound form (text field observed in code)
4. Document clarifies: "presentation before 36 weeks is not necessarily abnormal"

**Proposed exact future rule:**
```
IF ultrasound.presentation IN ('BREECH', 'TRANSVERSE', 'OBLIQUE')
   AND ultrasound.gestational_age_scan >= 36 THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = US-P01
    urgency = REVIEW_REQUIRED
    ML execution = SKIPPED
```

**Boundary cases:**
- Breech at 36+ weeks → HIGH
- Breech at 20 weeks → no trigger (gestational age context)
- Cephalic at term → no trigger
- "Vertex" (not in the recognized list) → no trigger (potential false negative)
- Transverse at term → HIGH

**Gestational context:** CRITICAL — presentation is only clinically significant near term. Current code has NO gestational-age check.

**Evidence/confirmation requirements:** Qualified ultrasound report with gestational age at scan

**Proposed risk classification:** HIGH (deterministic)

**Proposed urgency:** REVIEW_REQUIRED (not urgent unless combined with other factors)

**ML behavior:** Skipped if triggered

**Clinical interpretation wording:** "The recorded fetal presentation requires planning for hospital birth. Fetal presentation before 36 weeks may change."

**Suggested verification wording:** "Verify presentation by qualified ultrasound at or after 36 weeks. Check for other ultrasound findings."

**Required automated tests:**
- Breech at 36+ weeks → HIGH via US-P01
- Breech at 20 weeks → no US-P01 trigger (gestational context)
- Transverse at 37 weeks → HIGH
- Oblique at 38 weeks → HIGH
- Cephalic (or "Vertex") at term → no trigger
- Breech at term + ML LOW → HIGH (rule override)
- Presentation not in list (e.g., "Face", "Brow") → no trigger (documented limitation)

**Clinical approval needed:** YES — for adding gestational-age context to presentation rule

**Open questions:**
- What is the minimum gestational age for presentation assessment? Document suggests 36+ weeks but needs confirmation.
- Should the ultrasound form use a dropdown instead of free text for presentation?
- Should "Face" and "Brow" presentations be added to the recognized list?

---

### FACTOR ID: US-AF01

**Factor name:** Amniotic fluid abnormality

**Clinical domain:** Ultrasound / fetal assessment

**Document sources:**
- Document 1, Part 10 — Fetal Presentation, Amniotic Fluid, Placenta
- Document 4, Part 7 — Rewritten Ultrasound Findings, Rule US-AF01
- Document 4, Part 12 — `US-AF01 Amniotic fluid recorded Low or High → HIGH`

**Source confidence:** PRIMARY-GUIDELINE CITED (ISUOG)

**Current database fields:**
- `ultrasounds.amniotic_fluid` (string, nullable)

**Current model fields/casts:** No cast

**Current input form:**
- Ultrasound create/edit — "Amniotic Fluid" field

**Current controller validation:** `nullable|string|max:255`

**Current ClinicalRuleEngine behavior:**
```php
$amnioticFluid = strtoupper(trim((string) $ultrasound->amniotic_fluid));
if (in_array($amnioticFluid, ['LOW', 'HIGH'], true)) {
    $reasons[] = "Amniotic fluid abnormality ({$amnioticFluid})";
}
```

**Current ML usage:** Not a direct feature

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "low amniotic fluid returns fluid abnormality reason"
- No test for high fluid

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. DVP/AFI quantitation not used — relies on categorical label alone
2. No gestational-age context (normal AFV changes with gestation)
3. Free-text field

**Proposed exact future rule:** Current implementation matches document. Consider adding:
```
IF ultrasound.amniotic_fluid IN ('LOW', 'HIGH') THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = US-AF01
    urgency = PROMPT
    ML execution = SKIPPED
```

**Boundary cases:**
- "LOW" → HIGH
- "HIGH" → HIGH
- "Normal" or "Adequate" → no trigger
- Empty/null → no trigger

**Gestational context:** Amniotic fluid varies by gestation; extreme values are always clinically significant

**Evidence/confirmation requirements:** Qualified ultrasound report

**Required automated tests:**
- "LOW" → HIGH via US-AF01
- "HIGH" → HIGH via US-AF01
- "Normal" → no trigger
- "low" (case insensitive) → HIGH
- Low fluid + ML LOW → HIGH (rule override)

**Clinical approval needed:** NO for current implementation; YES if adding DVP/AFI quantitation

---

### FACTOR ID: US-FH01

**Factor name:** Fetal heartbeat abnormality

**Clinical domain:** Ultrasound / fetal assessment

**Document sources:**
- Document 1, Part 10 — Fetal Presentation, Amniotic Fluid, Placenta
- Document 4, Part 7 — Rewritten Ultrasound Findings, Rule US-FH01
- Document 4, Part 12 — `US-FH01 Fetal heartbeat recorded Weak, Abnormal, or Absent → HIGH; urgent when indicated`

**Source confidence:** PRIMARY-GUIDELINE CITED

**Current database fields:**
- `ultrasounds.fetal_heartbeat` (string, nullable)

**Current model fields/casts:** No cast

**Current input form:**
- Ultrasound create/edit — "Fetal Heartbeat" field (dropdown in form: "Normal", "Weak", "Abnormal", "Absent" observed)

**Current controller validation:** `nullable|string|max:255`

**Current ClinicalRuleEngine behavior:**
```php
$fetalHeartbeat = strtoupper(trim((string) $ultrasound->fetal_heartbeat));
if (in_array($fetalHeartbeat, ['WEAK', 'ABNORMAL', 'ABSENT'], true)) {
    $reasons[] = "Fetal heartbeat abnormality ({$fetalHeartbeat})";
}
```

**Current ML usage:** Not a direct feature

**Current decision effect:** HIGH

**Current test coverage:**
- `ClinicalRuleEngineTest`: "absent fetal heartbeat returns heartbeat abnormality reason"
- No tests for "WEAK" or "ABNORMAL"

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**CRITICAL SAFETY ISSUE:** Documents explicitly state: "Absent heartbeat must never be coded as automatic pregnancy-loss confirmation." Current code does not have separate handling for "ABSENT" vs "WEAK" — both go through the same HIGH path. Document proposes urgent confirmation process for absent FHR.

**Proposed exact future rule:**
```
IF ultrasound.fetal_heartbeat IN ('WEAK', 'ABNORMAL') THEN
    risk_level = HIGH
    decision_source = RULE_BASED
    reason_code = US-FH01
    urgency = PROMPT
    ML execution = SKIPPED

IF ultrasound.fetal_heartbeat == 'ABSENT' THEN
    risk_level = HIGH
    urgency = URGENT
    decision_source = RULE_BASED
    reason_code = US-FH01
    ML execution = SKIPPED
    action = "Urgent qualified confirmation. System cannot confirm pregnancy loss."
```

**Boundary cases:**
- "ABSENT" → HIGH + URGENT (different urgency from weak/abnormal)
- "WEAK" → HIGH + PROMPT
- "ABNORMAL" → HIGH + PROMPT
- "Normal" or "Present" → no trigger
- Empty/null → no trigger

**Gestational context:** FHR auscultation is gestational-age dependent; absent FHR before viability has different significance

**Evidence/confirmation requirements:** Qualified ultrasound report. Absent FHR requires urgent independent confirmation.

**Proposed risk classification:** HIGH (deterministic, with urgency escalation for absent)

**Proposed urgency:** PROMPT for weak/abnormal; URGENT for absent

**Clinical interpretation wording:** "The reported fetal heartbeat finding requires qualified verification and does not by itself confirm or exclude pregnancy loss."

**Required automated tests:**
- "ABSENT" → HIGH + urgent urgency
- "WEAK" → HIGH + prompt urgency
- "ABNORMAL" → HIGH + prompt urgency
- "Normal" → no trigger
- Absent FHR + ML LOW → HIGH (rule override)
- UI must NOT display "fetal death" or "miscarriage confirmed"

**Clinical approval needed:** YES — for urgency differentiation and absent-FHR protocol

**Open questions:**
- Should the rule bypass completeness check for absent FHR?
- What is the exact UI wording for absent FHR (must avoid diagnostic language)?
- Should the urgency metadata be exposed in the current explainability UI (not currently supported)?

---

### FACTOR ID: MAT-WARN

**Factor name:** Maternal warning symptoms

**Clinical domain:** Medical history / current warning symptoms

**Document sources:**
- Document 1, Part 11 — Maternal Warning Symptoms and Medical-History Conditions
- Document 4, Part 12 — not yet implemented as automated rules

**Source confidence:** PRIMARY-GUIDELINE CITED (WHO, NICE)

**Current database fields:**
- `medical_histories.severe_headache` (boolean)
- `medical_histories.visual_disturbance` (boolean)
- `medical_histories.chest_pain` (boolean)
- `medical_histories.shortness_breath` (boolean)
- (Other conditions: epilepsy, breast_mass, liver_disease, smoking, allergies, drug_intake, std_history, asthma, thyroid_disease, heart_disease, mental_health_condition)

**Current model fields/casts:** All boolean, nullable

**Current input form:**
- `resources/views/medical_histories/create.blade.php` — checkbox grid includes all conditions
- Stored in medical history, not in prenatal visit

**Current controller validation:** None specific (booleans default to null/false)

**Current ClinicalRuleEngine behavior:** NONE — warning symptoms from medical history are NOT evaluated by the rule engine

**Current ML usage:** NONE — these are not ML features

**Current decision effect:** No current effect (not evaluated)

**Current explainability:** NONE — not displayed in risk assessment

**Current test coverage:** NONE

**Current implementation status:** NOT IMPLEMENTED — DATA EXISTS BUT NOT USED

**Issues:**
1. Warning symptom data exists in `medical_histories` table but is never read by any clinical service
2. Documents (DOCU 1 Part 11, DOCU 4 Part 9) define 4 action levels: EMERGENCY TRANSFER, URGENT SAME-DAY, EXPEDITED CLINICAL REVIEW, ROUTINE
3. These are clinically critical symptoms for a lying-in clinic
4. No urgency metadata exists in the current system to convey action levels

**Proposed future rules (FUTURE RESEARCH — NOT READY TO IMPLEMENT):**

Document 1 Part 11 action-level mapping (requires clinical approval):
```
IF severe_headache == 1 OR visual_disturbance == 1 THEN
    action_level = URGENT_SAME_DAY
    association = "Possible hypertensive emergency escalation"
    decision = "Do not rely on BP value alone"

IF chest_pain == 1 OR shortness_breath == 1 THEN
    action_level = EMERGENCY_TRANSFER
    decision = "Immediate hospital referral"

IF epilepsy == 1 AND current_pregnancy THEN
    action_level = EXPEDITED_REVIEW
    decision = "Specialist comanagement required"
```

**Boundary cases:** Each symptom maps to an action level per document. Exact mapping requires clinical approval.

**Gestational context:** Some symptoms are pregnancy-stage dependent

**Evidence/confirmation requirements:** Patient-reported symptoms documented by staff

**Proposed risk classification:** Does not independently classify — determines urgency and action

**Proposed urgency:** Varies by symptom (EMERGENCY to ROUTINE)

**Clinical interpretation wording:** "Patient-reported symptoms require clinical assessment independent of automated risk classification."

**Clinical approval needed:** YES — full symptom-to-action mapping requires qualified clinical reviewer approval

**Open questions:**
- Should symptoms be evaluated before or after completeness check?
- How to integrate with existing referral workflow?
- Should symptoms be part of prenatal visit intake or only medical history?
- How to display urgency levels in current explainability UI (not designed for this)?

---

### FACTOR ID: ML-HIGH

**Factor name:** Machine-learning HIGH prediction

**Clinical domain:** ML output

**Document sources:**
- Document 3, Parts 1–12 — ML Architecture
- Document 4, Part 12 — `ML-HIGH Validated Random Forest output HIGH → HIGH (model-only referral signal)`
- Document 7, Part 4 — ML Explainability Specification

**Source confidence:** SYSTEM DESIGN REQUIREMENT

**Current database fields:** N/A (ML output is transient, stored in `prenatal_visits.ml_prediction`)

**Current model fields/casts:**
- `PrenatalVisit.ml_prediction` (nullable string — added in migration 2026_07_29_000000)
- `PrenatalVisit.ml_valid` (boolean, default false)

**Current ClinicalRuleEngine behavior:** N/A — ML is separate from deterministic rules

**Current ML usage:** 12-feature Random Forest (see Feature Array below)

Current 12 ML features in order:
1. `age` (float)
2. `gravida` (float)
3. `para` (float)
4. `bp_sys` (float)
5. `bp_dia` (float)
6. `weight` (float)
7. `gestational_age` (float)
8. `hypertension` (int)
9. `diabetes` (int)
10. `previous_cs` (int)
11. `miscarriage` (int)
12. `anemia` (int)

**Current decision effect:** HIGH (path 3 in hierarchy)

**Current explainability:**
- Decision source: Machine Learning (blue)
- Show prediction value and validation status
- Show deterministic rules note: "No HIGH-risk rule was triggered"
- Contribution wording per prediction outcome
- ML Assessment section in explainability card

**Current test coverage:**
- `MachineLearningServiceTest`: 11 tests (feature order, valid/invalid output, real predictions)
- `DecisionIntegrationServiceTest`: valid ML HIGH path, invalid ML path
- `ExplainabilitySprint7Test`: MACHINE_LEARNING display tests

**Current implementation status:** IMPLEMENTED AND ACCEPTABLE

**Issues:**
1. Scikit-learn model version warning (model v1.8.0, runtime v1.9.0)
2. Model not locally validated for Philippine population (per documents)
3. Only 12 features — many clinically important factors (warning symptoms, laboratory values) are not ML features
4. No model version tracking in the assessment record

**Proposed exact future rule:** Current implementation matches document specification. No change proposed.

**Boundary cases:** Only exact "LOW" or "HIGH" output is valid; anything else → invalid

**ML behavior:** Only executed when completeness check passes AND no deterministic rules triggered

**Clinical interpretation wording:** "The model assessment contributed to the final clinical assessment. The system does not rely on model output alone."

**Required automated tests:**
- Valid ML HIGH + complete records + no rules → HIGH (ML-only)
- Valid ML HIGH + incomplete records → not reached (completeness first)
- Valid ML HIGH + existing rule → not reached (rule overrides)

**Clinical approval needed:** NO for current implementation; YES for model version updates

**Open questions:**
- Should model version be stored per assessment?
- Should SHAP or feature-attribution explanations be implemented (per Document 7 Part 4)?
- What retraining policy applies?

---

### FACTOR ID: ML-LOW

**Factor name:** Machine-learning LOW prediction

**Clinical domain:** ML output

**Document sources:**
- Document 4, Part 12 — LOW-PATH
- Document 7, Part 4

**Source confidence:** SYSTEM DESIGN REQUIREMENT

**Current decision effect:** LOW (path 4 in hierarchy — safest path)

**Current implementation status:** IMPLEMENTED AND ACCEPTABLE

**Key safety rule:** LOW requires ALL THREE conditions:
1. All required records complete
2. No deterministic HIGH rule triggered
3. Valid ML LOW output

**Clinical interpretation wording:** "No HIGH factor was found from the completed records. Continue regular care and seek help for new symptoms."

---

### FACTOR ID: INC-PATH

**Factor name:** Assessment incomplete

**Clinical domain:** System workflow / data quality

**Document sources:**
- Document 4, Part 12 — INC-PATH
- Document 2, Part 2 — Data Validation Engine

**Source confidence:** SYSTEM DESIGN REQUIREMENT

**Current triggers:**
1. Missing required records (COMPLETENESS)
2. Invalid ML output (MACHINE_LEARNING_INVALID)
3. Both conditions simultaneously

**Current decision effect:** ASSESSMENT INCOMPLETE

**Current implementation status:** IMPLEMENTED AND ACCEPTABLE

**Document-proposed extension:** INC-PATH should also trigger for:
- Conflicting records (e.g., diabetes in prenatal visit but not in medical history)
- Stale data (ultrasound > specified age)
- Invalid values (biologically implausible)

These are NOT currently implemented.

---

### FACTOR ID: COMP-01

**Factor name:** Required record completeness

**Clinical domain:** Data quality / workflow

**Document sources:**
- Document 0 — Clinical Data Inventory
- Document 4, Part 12 — Section 4
- Document 6, Part 3 — Assessment Engine Architecture

**Source confidence:** SYSTEM DESIGN REQUIREMENT

**Current database coverage:**
- Medical History (19 condition fields)
- Ultrasound Record (11 fields)
- Birth Plan (17 fields)

**Current CompletenessValidator behavior:**
```php
// Checks existence of records, NOT field-level completeness
missingRequiredRecords() returns labels for absent records
```

**Current implementation status:** IMPLEMENTED BUT NEEDS REFINEMENT

**Issues:**
1. Record-existence check only — does not verify field-level completeness
2. No staleness check (e.g., ultrasound from 20 weeks ago may be stale at 36 weeks)
3. No stage-aware completeness (birth plan may not be needed in first trimester)
4. No consistency check across records (diabetes flag in visit vs history)

**Proposed future behavior (READY TO IMPLEMENT):**
- Add field-level completeness for critical fields within each record
- Add gestational-age-aware completeness (birth plan not required before 24 weeks)
- Add staleness warnings for ultrasound > 4 weeks old
- Add cross-record consistency checks

---

## Section 3 — Required Factor Groups Coverage Summary

| Group | Factors | Current Status |
|-------|---------|----------------|
| A. Maternal demographics | AGE-Y, AGE-A | IMPLEMENTED |
| B. Vital signs | BP-H, BP-URG | REFINED (Sprint 10 — urgency, pre-completeness, verification, explainability) |
| C. Medical history | DM-01, AN-01, heart_disease, asthma, thyroid, epilepsy, liver, mental health, allergies, smoking, drugs | DM-01, AN-01 implemented; others NOT IMPLEMENTED |
| D. Obstetric history | CS-01, RM-03 | IMPLEMENTED (migration issues) |
| E. Warning symptoms | severe_headache, visual_disturbance, chest_pain, shortness_breath | NOT IMPLEMENTED (data exists). Sprint 11 confirmed record-only: stored/displayed, never evaluated |
| F. Ultrasound | US-P01, US-AF01, US-FH01 | IMPLEMENTED (needs GA context) |
| G. Prenatal exam | fundic_height, FHT, fetal_movement, presentation, cervical, BOW | NOT USED in clinical logic |
| H. Completeness | COMP-01, missing records | IMPLEMENTED (field-level missing) |
| I. Referral | Referral workflow | IMPLEMENTED (basic) |
| J. ML | 12 features, ML-HIGH, ML-LOW, ML-INVALID | IMPLEMENTED |
| K. Pregnancy lifecycle | Delivered, referred, new pregnancy | IMPLEMENTED (workflow) |

---

## Section 4 — Cross-Factor Interactions

| Interaction | Documented? | Implemented? | Proposed Behavior |
|-------------|-------------|--------------|-------------------|
| Age + first pregnancy (AGE-A) | Yes (Doc 4 Part 1) | Yes | Current `elseif` mutually exclusive branch |
| Elevated BP + severe headache | Yes (Doc 4 Part 8) | No | Urgent BP action regardless of BP value |
| Elevated BP + visual disturbance | Yes (Doc 4 Part 8) | No | Urgent assessment; possible pre-eclampsia pathway |
| Diabetes + high amniotic fluid | Yes (Doc 4 Part 3,8) | Yes (Sprint 15, HIGH-gated) | Additive evidence `INT-DM-AF`; DM-01 + US-AF01 with observed fluid HIGH only; no polyhydramnios diagnosis; risk unchanged |
| Anemia + previous CS | Yes (Doc 4 Part 8) | No | Combined hospital birth planning + anemia management |
| Previous CS + malpresentation | Yes (Doc 4 Part 8) | Yes (Sprint 15) | Additive evidence `INT-CS-PRES`; CS-01 + US-P01 (Breech/Transverse/Oblique); no mode-of-birth/VBAC decision; risk unchanged |
| Multiple concurrent factors | Yes (Doc 4 Part 8) | Partially | Multiple reasons preserved; no interaction-specific explanation |
| Severe BP + any other factor | Yes (Doc 4 Part 8) | No | Urgent BP action takes precedence |
| Rule HIGH vs ML LOW | Yes (Doc 4 Part 5) | Yes | Rule overrides ML LOW |
| Incomplete records + HIGH rule | Yes (Doc 4 Part 12) | No | HIGH should be retained even if records incomplete — not current behavior |
| Conflicting ultrasound records | No explicit | No | Recency-based resolution |
| Stage-dependent completeness | Yes (Doc 6 Part 3) | No | Birth plan may not be needed in 1st trimester |

**Key gap:** Currently, if records are incomplete, the system returns ASSESSMENT INCOMPLETE WITHOUT evaluating rules. Documents propose that deterministic HIGH should be evaluated even when records are incomplete (HIGH should not be hidden by data gaps).

---

## Section 5 — Current Code Gaps

### 5.1 Fields Present in Models but Missing from Migrations

| Model | Field | Issue |
|-------|-------|-------|
| `Patient` | `previous_cs` | In `$fillable`, no migration column |
| `Patient` | `miscarriage` | In `$fillable`, no migration column |
| `PrenatalVisit` | `recommendation` | In `$fillable`, no migration column |

### 5.2 Fields Present in Migrations but Unused by Clinical Logic

| Table | Column | Status |
|-------|--------|--------|
| `medical_histories` | `severe_headache` | Stored, not evaluated |
| `medical_histories` | `visual_disturbance` | Stored, not evaluated |
| `medical_histories` | `chest_pain` | Stored, not evaluated |
| `medical_histories` | `shortness_breath` | Stored, not evaluated |
| `medical_histories` | `epilepsy` | Stored, not evaluated |
| `medical_histories` | `heart_disease` | Stored, not evaluated |
| `medical_histories` | `asthma` | Stored, not evaluated |
| `medical_histories` | `thyroid_disease` | Stored, not evaluated |
| `medical_histories` | `liver_disease` | Stored, not evaluated |
| `medical_histories` | `breast_mass` | Stored, not evaluated |
| `medical_histories` | `smoking` | Stored, not evaluated |
| `medical_histories` | `allergies` | Stored, not evaluated |
| `medical_histories` | `drug_intake` | Stored, not evaluated |
| `medical_histories` | `std_history` | Stored, not evaluated |
| `medical_histories` | `mental_health_condition` | Stored, not evaluated |

### 5.3 Form Fields Not Used by Clinical Logic

| Form | Field | Not used for |
|------|-------|-------------|
| Prenatal visit | `temperature` | No rule evaluates temperature |
| Prenatal visit | `fundic_height` | No rule evaluates fundic height |
| Prenatal visit | `fetal_heart_tone` | No rule (separate from ultrasound FHR) |
| Prenatal visit | `fetal_movement` | No rule (separate from ultrasound FM) |
| Prenatal visit | `presenting_part` | No rule (separate from ultrasound presentation) |
| Prenatal visit | `cervical_dilation` | No rule |
| Prenatal visit | `bag_of_water` | No rule |
| Ultrasound | `fetal_movement` | No rule |
| Ultrasound | `placenta_position` | No rule (no PAS screening) |
| Medical history | `other_specify` | No rule |

### 5.4 Clinical Rules Lacking Dedicated Fields

- No Hb value field for anemia
- No blood glucose / HbA1c for diabetes
- No urine protein for pre-eclampsia screening
- No interpregnancy interval tracking
- No previous cesarean scar type
- No fundic-height tracking for growth trends
- ~~No repeat-measurement protocol for BP~~ ✅ (Sprint 10 — implemented via repeat_bp_sys/dia, verification status, BP assessment service)

### 5.5 ML Features Without Sufficient Provenance

| Feature | Provenance Issue |
|---------|-----------------|
| `hypertension` (feature 8) | Boolean from visit form; no distinction pre-existing vs gestational |
| `diabetes` (feature 9) | Boolean; no type/timing/treatment info |
| `previous_cs` (feature 10) | Boolean; no scar/number/interpregnancy interval |
| `miscarriage` (feature 11) | Count; no trimester or cause |
| `anemia` (feature 12) | Boolean; no Hb value |

### 5.6 Documented Factors Absent from System

| Factor | Document Source | Status |
|--------|----------------|--------|
| Warning symptoms (severe headache, visual disturbance, chest pain, SOB) | Doc 1 Part 11 | Data exists, not evaluated |
| Epilepsy | Doc 1 Part 11 | Data exists, not evaluated |
| Heart disease | Doc 1 Part 11 | Data exists, not evaluated |
| Asthma | Doc 1 Part 11 | Data exists, not evaluated |
| Thyroid disease | Doc 1 Part 11 | Data exists, not evaluated |
| Liver disease | Doc 1 Part 11 | Data exists, not evaluated |
| Mental health condition | Doc 1 Part 11 | Data exists, not evaluated |
| Smoking | Doc 1 Part 11 | Data exists, not evaluated |
| Drug intake / substance use | Doc 1 Part 11 | Data exists, not evaluated |
| Fundic height < 3rd centile or abnormal trend | Doc 1 Part 9 | Data exists, not evaluated |
| Fetal movement — maternal concern | Doc 1 Part 9 | Data exists (ultrasound), not evaluated |
| Temperature (fever) | Doc 0 | Data exists, not evaluated |
| Urine protein | Doc 4 Part 2 | No field |
| Cervical dilation / preterm labor signs | Doc 4 Part 7 | Data exists, not evaluated |
| Multiple gestation | Doc 4 Part 7 | No field |
| Placenta previa / PAS | Doc 4 Part 5 | Data exists (placenta_position), not evaluated |

### 5.7 Known Issues (Pre-existing)

- `recommendation` column in `PrenatalVisit` `$fillable` but missing from migrations
- `previous_cs` in `Patient` `$fillable` but missing from migrations
- `miscarriage` in `Patient` `$fillable` but missing from migrations
- Referral feature test has 403 authorization failure (not clinical)
- ProfileTest soft-delete mismatch (not clinical)
- Scikit-learn model version warning: model 1.8.0, runtime 1.9.0

---

## Section 6 — Proposed Implementation Batches

### Sprint 10 — Blood-Pressure Verification and Structured BP Explainability ✅ COMPLETE

**Clinical factors:** BP-H, BP-URG

**Fields added (migration NOT executed):**
- `urgency` metadata (AssessmentResult + DB column ✅)
- `bp_assessment` JSON (AssessmentResult + DB column ✅)
- Repeat-BP fields: `repeat_bp_sys`, `repeat_bp_dia`, `repeat_bp_recorded_at`, `repeat_bp_recorded_by` ✅
- `bp_verification_status` (string — values: PENDING_REPEAT, REPEAT_COMPLETED, UNABLE_TO_REPEAT, NOT_REQUIRED) ✅

**Implementation summary:**
- `BloodPressureAssessmentService` — new service with `assess()` ✅
- `ClinicalRuleEngine` — BP logic removed (lines 23–29) ✅
- `RiskAssessmentService` — pre-completeness BP-URG urgent safety path ✅
- `DecisionIntegrationService` — urgency + bp_assessment passed through all paths ✅
- `AssessmentResult` — `$urgency` and `$bp_assessment` added (12 approved keys) ✅
- `PrenatalVisitController` — repeat BP validation, audit logging, persistence ✅
- `DashboardController` — urgent BP + pending repeat KPIs and priority lists ✅
- `RiskMonitoringController` — urgency + verification status filters ✅
- Blade views — urgency badge, BP Assessment section, repeat BP panel, filter dropdowns ✅
- 56 targeted tests pass; 0 regressions ✅

**Clinical approval needed:** YES — for BP-URG pre-completeness bypass and BP verification workflow. BP-H does NOT bypass completeness (PROMPT/REVIEW REQUIRED only).

**Key policy decisions (IMPLEMENTED, awaiting clinical reviewer sign-off):**
- BP-H (>=140/90) remains in post-completeness evaluation; does NOT bypass completeness check.
- BP-URG (>=160/110) is the ONLY pre-completeness urgent safety evaluation.
- Missing-record information preserved and displayed alongside BP-URG/BP-H outcomes.
- Repeat-BP: both-or-neither validation; editing initial BP clears repeat pair.
- Audit logging for BP actions (BP_REPEAT_RECORDED, BP_INITIAL_EDITED).

**Primary risks (mitigated):**
- BP-URG completeness bypass could mask missing records → preserved in missing_records and displayed alongside BP-URG outcome ✅
- Urgency display requires UI design → red URGENT badge + amber PROMPT badge + BP Assessment section implemented ✅
- Migration NOT executed → requires manual inspection before `php artisan migrate`

---

### Sprint 11 — Medical History Scope Stabilization ✅ COMPLETE

**Clinical factors:** DM-01, AN-01 (scope governance), MAT-WARN (deliberately NOT implemented)

**Scope:** Stabilize Medical History as a scoped, integrity-safe clinical record. NO new clinical rules. No migrations. BP/ML/decision-hierarchy untouched.

**Decisions implemented:**
- CDSS allowlist: only `diabetes` and `anemia` are CDSS-active from Medical History. The `ClinicalRuleEngine` consumes them from prenatal-visit inputs (`$visit->diabetes`/`$visit->anemia`), never from Medical History directly — documented source of truth, pinned by tests.
- MAT-WARN warning symptoms (`severe_headache`, `visual_disturbance`, `chest_pain`, `shortness_breath`) confirmed **record-only**: labeled "Informational — never used in the risk assessment" in UI; no engine path exists.
- Existence-based completeness gate verified and locked by tests (a Medical History satisfies the gate regardless of checked fields).
- Validated `store()`/`update()` (`$request->validate()` return value; `$request->boolean()` normalization; `other_specify` `required_if:other,1`; `patient_id` preserved on update).
- Duplicate prevention (application-level; redirects `create()`/`store()` to edit with "A Medical History record already exists for this pregnancy.").
- Delivered-patient protection on create/store/edit/update via the existing `Patient::isDelivered()` pattern.
- Extracted `app/Services/PatientAssessmentRecalculationService.php`; all `app(PrenatalVisitController::class)` calls removed from MedicalHistory/Ultrasound/BirthPlan controllers.
- Grouped, scoped forms + profile section with allowlist banner and stable `medical-history-form` id (fixes confirmation-modal `document.querySelector('form')` risk).
- 21 new tests; full suite 203 pass / 3 pre-existing unrelated failures.

**Clinical approval needed:** NO new rules introduced. Re-affirms DM-01 source-of-truth decision (visit inputs).

---

### Sprint 11 Hardening Patch — Source-of-Truth Wording, One-Way Sync, Recalculation Safety ✅ COMPLETE

**Clinical factors:** DM-01, AN-01 (source-of-truth governance), MAT-WARN (still record-only)

**Scope:** Implementation-only hardening. NO new clinical rules, no migrations, no BP/ML/decision-hierarchy changes. MAT-WARN symptom→action mapping remains deferred pending clinical approval.

**Decisions implemented (12):**
1. Prenatal Visit is the source of truth for dated diabetes/anemia CDSS inputs; Medical History is pregnancy-level background documentation + completeness evidence.
2. One-way monotonic sync limited to `diabetes` and `anemia` — visit Yes may set history true; visit No never clears.
3. No auto-create: a missing Medical History is never created by the sync.
4. Clearing a pregnancy-level condition requires explicit staff editing; no diagnosis inferred by sync.
5. New `MedicalHistoryConditionSyncService::syncConfirmedVisitConditions()` returning `changed` / `updated_fields` / `skipped_reason` / `visit_id`; saves only on actual change.
6. Sync runs after successful visit persistence, inside the existing store/update transaction, using persisted values; never on the assessment path; never triggers an assessment.
7. `MEDICAL_HISTORY_SYNC` audit entry only when history actually changes ("Medical History {field} updated from prenatal visit ID: {id}").
8. `PatientAssessmentRecalculationService::recalculateIncompleteVisits()` guards: missing patient → no-op; DELIVERED → no-op; all three records required; only `ASSESSMENT INCOMPLETE` visits recalculated; HIGH/LOW historical visits never rewritten.
9. Repeat-BP pair, verification status/note, BP assessment metadata, and existing `next_visit_date` preserved on recalculation.
10. Wording: "CDSS-Active Factors" → "Conditions Also Assessed During Prenatal Visits"; "Warning Symptoms & Notes" → "Legacy Historical or Recurring Concerns"; permanent "never used in the risk assessment" phrasing removed.
11. Patient profile presents diabetes/anemia as pregnancy-level background updates; optional note when a visit recorded a condition but no Medical History exists.
12. No migrations, thresholds, rules, routes, ML, or referral automation changed.
13. Delivered-patient sync guard: `syncConfirmedVisitConditions()` checks `$patient->isDelivered()` before any Medical History lookup and returns `skipped_reason = 'PATIENT_DELIVERED'` with no update, no audit, and no recalculation. **Synchronization is disabled for delivered (completed) pregnancies.**

**UI wording (exact):**
- Medical History banner: "Diabetes and Anemia are also assessed during prenatal visits and may affect that visit's CDSS result. This Medical History record stores pregnancy-level background information and is not directly submitted to the risk engine."
- Legacy group note: "These fields store previously reported or recurring concerns. They do not confirm that a symptom is present during the current prenatal visit and are not evaluated by the current CDSS."
- Prenatal visit Risk Factors note: "Diabetes and anemia are assessed for this visit. When marked Yes, the existing Medical History background record will also be updated. A No value does not automatically remove a previously recorded condition."

**Tests added/updated:** MedicalHistoryConditionSyncServiceTest (13 incl. delivered-patient guard), PrenatalVisitConditionSyncTest (10), PatientAssessmentRecalculationServiceTest (9), MedicalHistoryScopeTest (19 incl. prenatal page coverage), ClinicalRuleEngineTest renamed. Full suite 233 pass / 3 pre-existing unrelated failures.

**Clinical approval needed:** NO new rules. MAT-WARN remains deferred.

---

### MAT-WARN Integration (DEFERRED — requires clinical approval)

**Clinical factors:** MAT-WARN (severe headache, visual disturbance, chest pain, shortness of breath)

**Status:** NOT IMPLEMENTED. Sprint 11 deliberately kept these fields record-only. No symptom → action-level mapping exists.

**Fields required:** None (data exists in `medical_histories`)

**Services affected (when approved):**
- `ClinicalRuleEngine` — add warning symptom evaluation
- `DecisionIntegrationService` — add EMERGENCY/URGENT paths
- `AssessmentResult` — already has urgency capacity (from Sprint 10)

**UI affected:**
- Patient profile: warning symptom display with urgency
- Risk Monitoring: action-level column
- Referral: automated reason population

**Tests required (when approved):**
- Each symptom → correct action level
- Symptom + normal BP → still triggers urgency
- Multiple symptoms → highest urgency applied
- Referral reason auto-population

**Clinical approval needed:** YES — symptom-to-action mapping

---

### Later Batches

**Batch 3 — Gestational-age-contextual ultrasound logic (US-P01, US-AF01)**
- Add GA check to presentation rule
- Add structured dropdown for presentation field
- Tests for pre-36-week vs post-36-week presentation

**Batch 4 — Laboratory-supported anemia logic (AN-01)**
- Add Hb field to prenatal visit or lab table
- Implement WHO trimester-specific thresholds
- Deprecate boolean anemia flag as sole source

**Batch 5 — Diabetes provenance and status (DM-01)**
- Resolve source-of-truth (visit vs history)
- Add diabetes type/timing/treatment fields
- Add DM-INC for incomplete treatment detail

**Batch 6 — Stage-aware completeness (COMP-01)**
- Add field-level completeness checks
- Add gestational-age-dependent required records
- Add data staleness warnings

**Batch 7 — Pregnancy outcome / EDD monitoring**
- EDD-triggered outcome capture
- Postpartum follow-up tracking
- Delivered-pregnancy data completeness

---

## Section 7 — Approval Register

| Factor ID | Proposed Rule | Evidence Status | Clinic-Policy Decision Needed | Qualified Clinical Reviewer Needed | Approved? | Date | Reviewer | Notes |
|-----------|--------------|-----------------|------------------------------|-----------------------------------|-----------|------|----------|-------|
| AGE-Y | Age < 19 → HIGH | WHO, ACOG cited | Yes | Yes | PENDING | — | — | Current implementation matches; requires reviewer sign-off |
| AGE-A | Age >= 35 + G1P0 → HIGH | ACOG (narrowed) | Yes (narrower than guidelines) | Yes | PENDING | — | — | Consider adding AGE-40 rule |
| BP-H | BP >= 140/90 → HIGH (post-completeness) + PROMPT urgency | NICE, WHO, ISSHP | Yes (completeness-bypass policy) | Yes | PENDING | — | — | Implemented Sprint 10: BP-H remains post-completeness; PROMPT urgency; repeat-BP workflow implemented |
| BP-URG | BP >= 160/110 → HIGH + URGENT_CLINICAL_REVIEW (pre-completeness) | NICE, WHO | Yes (completeness bypass) | Yes | PENDING | — | — | Implemented Sprint 10: pre-completeness urgent safety override; URGENT_CLINICAL_REVIEW metadata; repeat-BP resolution; missing records preserved |
| DM-01 | Diabetes → HIGH | WHO, NICE, ACOG | Yes (source reconciliation) | Yes | PENDING | — | — | Needs medical history integration |
| AN-01 | Anemia → HIGH | WHO 2024 | Yes (lab value addition) | Yes | PENDING | — | — | Needs Hb field |
| CS-01 | Previous CS → HIGH | NICE, RCOG | Yes (migration fix) | Yes | PENDING | — | — | Migration fix required |
| RM-03 | Miscarriage >= 3 → HIGH | RCOG (conservative) | Yes (>=2 vs >=3) | Yes | PENDING | — | — | Migration fix required |
| US-P01 | Abnormal presentation → HIGH | ISUOG | Yes (GA context) | Yes | PENDING | — | — | Needs GA >= 36 check |
| US-AF01 | Low/High fluid → HIGH | ISUOG | Yes | Yes | PENDING | — | — | Current matches; needs reviewer sign-off |
| US-FH01 | Abnormal FHR → HIGH | ISUOG | Yes (absent FHR protocol) | Yes | PENDING | — | — | Needs urgency differentiation |
| MAT-WARN | Warning symptoms → action levels | WHO, NICE | Yes | Yes | PENDING | — | — | Sprint 11 confirmed record-only; symptom-to-action mapping deferred pending clinical sign-off |
| ML-HIGH | ML HIGH → HIGH (no rule) | System design | Yes | Yes | PENDING | — | — | Current matches; ML provenance documentation required |
| ML-LOW | ML LOW → LOW (complete + no rule) | System design | Yes | Yes | PENDING | — | — | Current matches; ML provenance documentation required |
| INC-PATH | Incomplete → INCOMPLETE | System design | Yes | Yes | PENDING | — | — | Current matches; needs reviewer sign-off |
| COMP-01 | Stage-aware and field-level completeness | System design / document-supported | Yes | Yes | PENDING | — | — | Define gestational-stage requirements, critical fields, and data staleness |

---

## Section 8 — Defense Summary

### Why Document-to-Code Traceability?

The project operates in a clinical domain where patient safety depends on correct, verifiable, and explainable logic. Document-to-code traceability ensures:
- Every automated rule is linked to a cited evidence source
- Implemented behavior can be audited against the specification
- Clinical reviewers can evaluate rules without reading PHP code
- Future maintainers understand why each rule exists

### Why Deterministic Safety Rules Override ML?

International guidance (WHO, NICE, FDA) emphasizes that clinical decision support must be transparent and reviewable. Deterministic rules derived from published guidelines provide:
- Verifiable, inspectable logic
- No hidden black-box behavior
- Immediate correction when guidelines change
ML identifies patterns but cannot guarantee safety-critical rule application. The hierarchy ensures known risk factors are never overridden by statistical inference.

### Why Missing Information Cannot Become LOW?

"Absence of data is not evidence of absence." The system uses `ASSESSMENT INCOMPLETE` when required information is missing to prevent false reassurance. A LOW result means the system actively found no HIGH factor from the available data — this conclusion is only valid when the required data are present. This principle is cited in DOCU 4 Part 12 and is a foundational safety invariant.

### Why ML Is Advisory

The Random Forest model:
- Has not been locally validated in a Philippine population
- Uses only 12 features (missing many clinically important inputs)
- May contain dataset bias from its training population
- Can produce invalid or unavailable output
ML is treated as supportive information, not authoritative. Deterministic rules and human clinical judgment always take precedence.

### Why the System Uses Only HIGH / LOW / INCOMPLETE

Per DOCU 5 Part 2 and DOCU 4 Part 12:
- The project has no validated threshold for a fourth category (MODERATE, VERY HIGH)
- Multiple HIGH factors increase urgency and action specificity, not the risk class
- A lying-in clinic's primary decision is: needs higher-level care (HIGH) or can continue routine care (LOW)
- INCOMPLETE prevents false LOW when data are insufficient

### Why Urgency Is Separate from Risk Classification

Risk classification (HIGH vs LOW) determines whether closer review is needed. Urgency (ROUTINE → EMERGENCY) determines how quickly action is required. A patient can be HIGH with routine urgency (e.g., age < 19 with no other factors) or HIGH with urgent action (e.g., BP >= 160/110 → URGENT_CLINICAL_REVIEW). Separating these allows precise clinical communication.

**Implemented in Sprint 10:** Urgency is stored as a dedicated DB column (`urgency`) and structured BP assessment data (`bp_assessment` JSON), displayed as a red URGENT badge in patient profile, filterable in Risk Monitoring, and counted in dashboard KPIs.

### Why Clinical Approval Is Required Before Rule Expansion

Adding new automated rules affects:
- Patient safety (false positives cause unnecessary referrals; false negatives miss danger signs)
- Clinic workflow (more HIGH patients may overwhelm referral capacity)
- Staff reliance on automation (deskilling risk)
- Liability and accountability boundaries
Every new rule requires qualified clinical review to confirm:
- The evidence adequately supports automated decision-making
- The threshold is appropriate for a Philippine lying-in clinic
- The explainability, referral, and follow-up workflows are in place

### Why the System Supports and Does Not Replace Qualified Personnel

The CDSS provides advisory information to trained maternity staff. It does not:
- Diagnose disease
- Prescribe treatment
- Interpret diagnostic images
- Decide delivery mode or timing
- Replace clinical judgment
This positioning aligns with FDA 2026 CDS guidance, WHO AI ethics principles, and NICE evidence standards for digital health technologies. The system's primary clinical value is standardized risk flagging, referral support, documentation, and explainability — not autonomous decision-making.

## Sprint 14 Addendum — Structured Clinical Factor Evidence

### Purpose

Each triggered deterministic factor now has a structured, immutable evidence record in addition to its legacy reason string. The clinical rules, thresholds, decision hierarchy, and BP behavior are unchanged; this addendum only documents how the explainability layer maps to code.

### Evidence Object Model

- **Registry** — `app/Support/ClinicalFactorRegistry.php` (metadata only, 11 codes: `AGE-Y`, `AGE-A`, `BP-H`, `BP-URG`, `DM-01`, `AN-01`, `CS-01`, `RM-03`, `US-P01`, `US-AF01`, `US-FH01`). Unknown codes return `null` and never receive invented metadata.
- **Value Object** — `app/ValueObjects/ClinicalFactorEvidence.php` (immutable; eleven approved keys: code, label, category, source_type, source_fields, observed_value, threshold_or_rule, decision_effect, urgency, explanation, suggested_action).
- **Rule engine** — `ClinicalRuleEngine::evaluateDetailed()` returns evidence in rule order; `evaluate()` remains a compatible label wrapper so legacy reason strings stay byte-identical.
- **BP adapter** — `BloodPressureFactorEvidenceMapper` maps the Sprint 10 `bp_assessment` array to `BP-H`/`BP-URG` evidence only; unknown/not-triggered → no evidence.
- **Decision integration** — `DecisionIntegrationService::decide()` / `decideUrgentBp()` carry `factor_evidence` through COMPLETENESS (BP alert only), RULE_BASED, and BP-URG paths; ML and ML_INVALID paths store `[]`.
- **Persistence** — `PrenatalVisit.factor_evidence` (nullable JSON, array cast). Legacy records with `NULL` continue rendering via `rule_reasons`/`risk_reasons` fallbacks; no historical record is backfilled or rewritten.

### Factor Metadata Reference

Each registry entry carries: staff-friendly label, category (MATERNAL_DEMOGRAPHICS / VITAL_SIGNS / CURRENT_CONDITION / OBSTETRIC_HISTORY / ULTRASOUND), source type and fields, threshold-or-rule text, decision effect (HIGH), urgency, explanation, and suggested action. Placeholder labels (`{count}`, `{value}`) are always overridden by the rule engine at runtime (e.g., `History of 3 miscarriage(s)`, `Abnormal fetal presentation (BREECH)`).

## Sprint 13 Checkpoint B Addendum — Context-Aware Assessment Architecture and Rule Governance

### Purpose

The assessment engine now persists a reproducibility metadata document alongside every result. This addendum is documentation-only for the factor matrix: **no factor, threshold, decision hierarchy, or clinical behavior changed**. The Sprint 13 metadata is a separate, non-clinical channel that snapshots the context, records the decision path, surfaces data-quality items that need staff verification, and carries interaction evidence governed by an explicit allowlist.

### Metadata Object Model

- **Context snapshot** — `AssessmentContext` (immutable) + `AssessmentContextBuilder`: assessment date, patient id/status, GA, LMP/EDD, selected ultrasound (id/date and its three findings `presentation`, `amniotic_fluid`, `fetal_heartbeat` via `ultrasound_inputs`, controlled by `UltrasoundSnapshot`), active medical-history/birth-plan presence and duplicate counts, visit. The ultrasound is selected **once** deterministically (`scan_date DESC`, `created_at DESC`, `id DESC`); the DQ missing-fields check and the rule engine consume the snapshot, never `Ultrasound::find()`. The same context instance drives the engine and the metadata, so the snapshot always matches what was evaluated.
- **Interaction evidence** — `ClinicalInteractionRegistry` + `ClinicalInteractionEvidence` + `ClinicalInteractionEngine`: candidate interaction codes (`CLIN-INTER-*`) are all DRAFT/DEFERRED; `activeCodes()` is **empty** in Sprint 13, so the engine emits `[]` with no DB/ML/scoring. No interaction affects any factor matrix entry.
- **Data-quality flags** — `DataQualityFlagRegistry` + `DataQualityFlag` + `AssessmentDataQualityService`: ACTIVE flags `DQ-SOURCE-FUTURE-DATED`, `DQ-ULTRASOUND-MISSING-FIELDS`, `DQ-DUP-MEDICAL-HISTORY`, `DQ-DUP-BIRTH-PLAN`; DEFERRED `DQ-LMP-MISSING`, `DQ-EDD-MISSING`, `DQ-GA-DATE-MISMATCH`, `DQ-ULTRASOUND-STALE`. Severities INFO / VERIFY / IMPORTANT. Flags never enter `factor_evidence`, never classify HIGH, never affect urgency or counts — they only request staff verification.
- **Decision trace** — `DecisionTraceStep` + `DecisionTraceBuilder`: approved 7-step pipeline `CONTEXT_BUILT` → `URGENT_BP_CHECK` → `COMPLETENESS_CHECK` → `STANDALONE_RULE_EVALUATION` → `INTERACTION_RULE_EVALUATION` → `ML_EVALUATION` → `FINAL_DECISION`, statuses `COMPLETED`/`TRIGGERED`/`SKIPPED`/`BLOCKED`, derived from the final result only (never re-evaluates, so it cannot contradict the outcome). When BP-URG overrides completeness, missing records are preserved but do not block the urgent result.
- **Versions** — `AssessmentVersion` records assessment engine, clinical-rule, and context versions per result.
- **Persistence** — `AssessmentMetadataSerializer` produces the scoped document stored in `PrenatalVisit.assessment_metadata` (nullable JSON, array cast; additive migration `2026_08_05_000002_...` NOT executed). Legacy `NULL` rows keep rendering via existing fallbacks.

### Clinical Safety Position

Sprint 13 adds reproducibility and governance without touching the clinical layer: the factor allowlist (11 codes), BP thresholds, completeness ordering, decision hierarchy, ML path, referrals, and sync logic are unchanged. Any future promotion of an interaction or activation of a deferred DQ flag requires clinical approval before it may influence assessment output.

---

## Sprint 15 Phase 15B Addendum — Controlled Clinical Interaction Activation

### Purpose

Sprint 15 activates exactly three **additive explainability** interactions on the existing RULE_BASED HIGH path. Interactions never replace factor evidence and never change the final risk classification, urgency, precedence, ML invocation, or decision hierarchy. All other interaction candidates remain DRAFT or DEFERRED.

### ACTIVE interactions (Sprint 15)

| Code | Contributors | Observed-value condition | Decision behavior | Boundary / limitation |
|------|--------------|--------------------------|-------------------|------------------------|
| `INT-BP-DM` | BP-H + DM-01 | none | Additive evidence only; risk stays HIGH | No preeclampsia diagnosis; no BP threshold/urgency change |
| `INT-DM-AF` | DM-01 + US-AF01 | `ultrasound_inputs.amniotic_fluid` = HIGH | Additive evidence only; risk stays HIGH | LOW or missing/malformed fluid never triggers; no polyhydramnios cause/severity diagnosis; US-AF01 unchanged |
| `INT-CS-PRES` | CS-01 + US-P01 | none (presentation is any of Breech/Transverse/Oblique) | Additive evidence only; risk stays HIGH | No mode of birth / VBAC determination; hospital-level planning support only |

### Contract positions

- **Additive-only:** `factor_evidence` keeps each standalone factor row; `interaction_evidence` is a separate list. A detected interaction never deletes or overwrites contributing factor evidence.
- **Value gate:** the controlled evaluated `amniotic_fluid` value is read from `AssessmentContext::ultrasound_inputs` (already sanitized by `UltrasoundSnapshot`), compared case-insensitively; the interaction only fires when the value is HIGH. LOW, missing, null, or malformed values never satisfy the condition. No DB field added.
- **Governance:** statuses ACTIVE, `rule_version` 1.1.0, `decision_effect` and `urgency` null. `INT-WARNING-BP`, `INT-US-PRESENTATION-GA`, `INT-ANEMIA-LAB`, `INT-SYMPTOM-CONDITION`, `INT-PERSISTENT-FINDING` remain DRAFT/DEFERRED.
- **Versioning:** `CLINICAL_RULE_VERSION` stays `1.1.0` (bumped `1.0.0 → 1.1.0` in `AssessmentVersion` for Sprint 15); historical persisted assessments keep their recorded version. No separate interaction version system introduced.
- **Observed-context preservation (Phase 15C):** each ACTIVE interaction declares `observed_context_keys` (dotted paths) in the registry. The engine copies those exact controlled values (e.g. `ultrasound_inputs.amniotic_fluid = HIGH`, `ultrasound_inputs.presentation`) into `interaction_evidence.observed_context`. It never stores the whole `AssessmentContext`, never stores PII, models, or ultrasound remarks/notes, and never duplicates BP thresholds/snapshots (`INT-BP-DM` has no keys). LOW/missing values stay absent.
- **Clinician-facing UI (Phase 15D):** the patient profile renders a dedicated **Clinical Interactions Identified** section (label, code, contributing factors, clinician-readable observed values, explanation, suggested action) only when valid persisted evidence exists; Risk Monitoring shows a compact interaction-count badge; the printable patient record includes the same interaction table. Observed context is translated through a strict whitelist — raw keys, internal structure, patient IDs, and technical terms are never shown. Blade never re-evaluates clinical logic and never invents BP snapshots. Legacy/null metadata and malformed/unknown interaction rows render safely.

### Deferral note

All other candidates (warning-symptom+BP, anemia+Hb, placenta, GA-gated presentation, fetal movement, fundal height, new labs, BP-URG combination, clusters, scores) remain deferred exactly as documented in Phase 14C. No scores, percentages, MODERATE/VERY HIGH/EXTREME, or treatment/prescription logic were added.
