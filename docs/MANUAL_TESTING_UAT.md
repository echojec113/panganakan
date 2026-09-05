# Manual Testing / User Acceptance Test (UAT) Plan — Maternity CDSS

- **Project:** Maternity Clinical Decision Support System (prenatal risk assessment)
- **Repository root:** `C:\Users\BJ\panganakan`
- **Stack (verified):** Laravel 12, PHP 8.3, MySQL, Blade + Tailwind, Python + Random Forest (`maternal-risk-ml/predict.py`), DomPDF, Laravel Mail.
- **Document type:** Manual test case plan. Read-only exercise — no application code, schema, or data were modified to produce it.
- **Prepared:** based on the actual repository implementation (controllers, services, models, views, routes, migrations).

---

## 1. Discovered Module Inventory (from the real codebase)

| # | Module | Primary routes (verified in `routes/web.php`) | Controller |
|---|--------|----------------------------------------------|-----------|
| 1 | Authentication (Breeze) | `/login`, `/register`, `/forgot-password`, `/reset-password`, `/confirm-password` | Breeze (`routes/auth.php`) |
| 2 | Dashboard | `GET /dashboard` | `DashboardController` — role-split admin/staff |
| 3 | Profile | `GET/PATCH /profile`, `DELETE /profile` | `ProfileController` |
| 4 | Patients | `GET/POST/PUT/DELETE /patients`, `GET /patients/trashed`, `POST /patients/{id}/restore`, `POST /patients/{id}/deliver`, `POST /patients/{id}/start-new-pregnancy`, `GET /patients/{patient}` (show, wildcard last) | `PatientController` |
| 5 | Delivered records | `GET /patients/delivered`, `/patients/delivered/{id}/history`, `/babies`, `/print-babies` | `PatientController` |
| 6 | Patient download | `POST /patients/{id}/download` (PDF/CSV) | `PatientController` |
| 7 | Prenatal Visits | `resource('prenatal-visits')` (staff) | `PrenatalVisitController` |
| 8 | Medical History | `resource('medical-histories')` (staff) | `MedicalHistoryController` |
| 9 | Birth Plan | `resource('birth-plans')` (staff) | `BirthPlanController` |
| 10 | Ultrasound | `GET /ultrasound/create/{patient}`, `POST /ultrasound/store`, `GET/PUT /ultrasound/{id}` edit/update | `UltrasoundController` |
| 11 | Risk Monitoring | `GET /risk-monitoring`, `GET /risk-monitoring/analytics` | `RiskMonitoringController` |
| 12 | Referrals | `GET /referrals`, `/referrals/analytics`, `/referrals/{id}`, `/referrals/{id}/print`; staff: create/store/complete/refuse/cancel | `ReferralController` |
| 13 | Pregnancy Outcome Monitoring | `GET /pregnancy-outcomes`; staff: `POST .../still-pregnant`, `POST .../unable-to-contact` | `PregnancyOutcomeController` |
| 14 | Audit Logs | `GET /audit-logs` (admin-only check in controller) | `AuditLogController` |
| 15 | Staff Management | `resource('staff')->except(['show'])` (admin-only check) | `StaffController` |
| 16 | Emails | `App\Mail\PrenatalVisitReminderMail`, `PrenatalVisitScheduleUpdatedMail` | invoked from `PrenatalVisitController` |

**Access model (verified):** `bootstrap/app.php` aliases a `staff` middleware (`StaffMiddleware`, requires `role === 'staff'`, else 403). All clinical write routes sit behind `staff`. View-only pages (`patients.show`, referrals index/show/print, risk monitoring, pregnancy-outcomes index, audit-logs) sit behind `auth`. Staff CRUD and AuditLogs enforce an admin-only `checkAdmin()` in the controller (`abort(403)`).

---

## 2. Environment & Preconditions

1. PHP ≥ 8.3 with required extensions, MySQL, Composer, and a working `python` (or `PYTHON_PATH` env pointing to a python binary) for the ML step.
2. `cp .env.example .env`, configure DB (MySQL) and `PYTHON_PATH`; `php artisan key:generate`.
3. `composer install`, `npm install && npm run build` (Tailwind/assets).
4. `php artisan migrate --seed` (or fresh). Verify `python maternal-risk-ml/predict.py ...` runs and prints `HIGH`/`LOW`.
5. For email tests: local mail driver (e.g., `MAIL_MAILER=log`) — assert via `storage/logs/laravel.log`.
6. Create test users: one admin, one staff (below).

## 3. Test Data / Users

| ID | Name | Email | Role | Notes |
|----|------|-------|------|-------|
| U1 | Admin One | `admin@test.com` | `admin` | Used for dashboard-level and admin-only sections |
| U2 | Staff One | `staff@test.com` | `staff` | Clinical writes; records BP repeat / follow-up provenance |

Each section below lists its own necessary rows. Start each scenario from a clean test database where stated.

---

## 4. Test Case Table — Guide

Each case uses: **ID** / **Preconditions** / **Test Steps** / **Expected Result** / **Actual Result** (blank) / **Pass–Fail** (blank) / **Remarks** (blank).

---

## AUTH — Authentication

### AUTH-001 — Login with valid credentials
- **Preconditions:** Users U1, U2 exist.
- **Steps:** Open `GET /login`; enter staff email + password; submit.
- **Expected:** Authenticated and redirected to `/dashboard` (role-based dashboard). Root `/` redirects to `login`.

### AUTH-002 — Login with invalid password
- **Steps:** Enter a valid email + wrong password.
- **Expected:** Validation error "These credentials do not match our records."; not authenticated.

### AUTH-003 — Protected pages while logged out
- **Steps:** Logged out, try `GET /dashboard`, `GET /patients/{id}`, `GET /referrals`, `GET /risk-monitoring`.
- **Expected:** Redirected to `/login` for all.

### AUTH-004 — Registration default role
- **Steps:** Logged out, `GET /register`, submit a new name/email/password.
- **Expected:** Account created; `users.role` uses the column default (verify value from migration — do not assume staff). Confirm a registered default-role user cannot reach staff-only routes.

### AUTH-005 — Password reset flow
- **Steps:** `GET /forgot-password` → submit email → follow reset link → set new password → login.
- **Expected:** Reset token works; login succeeds with the new password.

### AUTH-006 — Logout
- **Steps:** While logged in, click Logout.
- **Expected:** Session ends; redirected to login; protected pages require login again.

---

## PROFILE

### PROFILE-001 — Update profile info
- **Steps:** `GET /profile`, change name, save.
- **Expected:** Name updated; flash `profile-updated`; email change resets `email_verified_at`.

### PROFILE-002 — Update password
- **Steps:** Provide current + new password.
- **Expected:** Password updated; can log in with the new one.

### PROFILE-003 — Delete account
- **Steps:** Enter current password, delete.
- **Expected:** Account soft-deleted; session cleared; redirect to `/`.

---

## SEC — Access Control & Roles

### SEC-001 — Staff-only write matrix (admin blocked)
- **Steps:** As admin, attempt each staff write: patient create/update/destroy, restore, deliver, update-baby, start-new-pregnancy, still-pregnant, unable-to-contact, referrals create/store/complete/refuse/cancel, ultrasound create/store/update, prenatal-visits create/store/update/destroy, medical-histories create/store/update, birth-plans create/store/update/destroy.
- **Expected:** All return 403. Admin cannot modify any clinical data. (View pages remain 200.)

### SEC-002 — Admin-only: Staff management
- **Steps:** As staff, `GET /staff`, `GET /staff/create`, etc.
- **Expected:** 403 via `StaffController::checkAdmin()` (`abort(403)`).

### SEC-003 — Admin-only: Audit logs
- **Steps:** As staff, `GET /audit-logs`.
- **Expected:** 403. As admin, 200.

### SEC-004 — Read-only pages accessible to both roles
- **Steps:** As admin and as staff, open `GET /patients/{id}`, `GET /referrals`, `GET /risk-monitoring`, `GET /pregnancy-outcomes`, `GET /patients/delivered`.
- **Expected:** 200 for both roles. Follow-up/action buttons render only for non-admin (see OUT-003 / FUP-005).

### SEC-005 — Restore route protection
- **Steps:** As admin, `POST /patients/{id}/restore`.
- **Expected:** 403 (route sits inside `staff` group).

---

## PAT — Patients (CRUD)

### PAT-001 — Patient list shows only ONGOING + "my" filter
- **Preconditions:** ≥2 ONGOING patients; at least one assigned to the logged-in staff (`assigned_staff_id`). One DELIVERED patient exists.
- **Steps:** `GET /patients` — observe list; use the "My patients" tab (`filter=my`).
- **Expected:** Default list = `status = 'ONGOING'` only (DELIVERED not listed). `filter=my` limits to `assigned_staff_id = auth()->id()`. Stats cards (Total / With PhilHealth / High-Risk) render.

### PAT-002 — Create patient — happy path
- **Steps:** `GET /patients/create`; fill: first/last name (letters+spaces only), birthdate (before today), age 10–60, address, contact `09XXXXXXXXX`, civil status in {Single, Married, Widowed}, PhilHealth 0/1 (+ number if 1), gravida ≥0, para ≥0, previous_cs 0/1, miscarriage ≥0, lmp, edd (after lmp).
- **Expected:** Patient created; `assigned_staff_id = auth()->id()`; redirect to `patients.index` with success; audit log `CREATE`/`PATIENT` written.

### PAT-003 — Create patient — validation
- **Steps:** Submit contact `09123`; name with digits; para > gravida; miscarriage > gravida; edd ≤ lmp; age 5.
- **Expected:** Field errors per rules — contact regex `/^09\d{9}$/`; names `regex:/^[a-zA-Z\s]+$/`; "Para cannot exceed Gravida"; "Miscarriage cannot exceed Gravida"; edd must be after lmp; age min 10. Nothing persisted.

### PAT-004 — View patient profile
- **Preconditions:** ONGOING patient with visits, medical history, ultrasound, birth plan.
- **Steps:** `GET /patients/{id}`.
- **Expected:** Profile renders latest assessment (created_at desc, id desc), visit history newest-first, monitoring state card (see OUT section). "Add prenatal visit" only enabled when MedicalHistory + Ultrasound + BirthPlan all exist (`canAddPrenatalVisit`).

### PAT-005 — Edit patient
- **Steps:** `GET/PUT /patients/{id}/edit`; change name/address; also try invalid LMP/EDD.
- **Expected:** Update persists; same validation as create; audit `UPDATE`/`PATIENT`.

### PAT-006 — Soft delete + cascade restore
- **Steps:** Delete an ONGOING patient; open `GET /patients/trashed`; restore.
- **Expected:** On delete: patient + visits + ultrasounds + babies + birth plan + medical history soft-deleted (cascade in `Patient::boot`). Trashed list shows them. Restore restores all related (cascade restore). Audit `DELETE`/`PATIENT`.

### PAT-007 — Baby edit immutability on closed pregnancies
- **Preconditions:** One DELIVERED patient with a baby and one ONGOING patient with a baby.
- **Steps:** Attempt `POST /patients/babies/{id}` (updateBaby) for the delivered baby vs the ongoing baby.
- **Expected:** DELIVERED/REFERRED baby → JSON 403 "Baby information can no longer be edited once the pregnancy is closed." ONGOING baby → 200 with updated JSON.

### PAT-008 — Start new pregnancy
- **Preconditions:** A DELIVERED patient with no active ONGOING duplicate.
- **Steps:** `POST /patients/{id}/start-new-pregnancy` with lmp/edd/address/contact.
- **Expected:** New `ONGOING` patient row (same identity fields, `gravida+1`, `para` preserved, `previous_cs`/`miscarriage` copied, `delivery_date=null`); redirect to new patient show; audit `CREATE`.

### PAT-009 — Start new pregnancy — guards
- **Steps:** (a) attempt on a non-DELIVERED patient; (b) attempt when an ONGOING active pregnancy exists for the same name+birthdate.
- **Expected:** (a) error "New pregnancy can only be started from a delivered patient record."; (b) error "This patient already has an active ongoing pregnancy record." Nothing created.

---

## PAT-DEL — Delivered Records / Delivery Workflow (Sprint 17C)

### PAT-DEL-001 — Confirm delivery
- **Preconditions:** ONGOING staff-assigned patient.
- **Steps:** Profile → "Mark as delivered" → fill delivery_date (≤ today), delivery_location ∈ {THIS_CLINIC, ANOTHER_FACILITY, HOME, OTHER}, confirmation_source ∈ {CLINIC_RECORD, PATIENT_REPORT, OTHER_FACILITY_REPORT, OTHER}, notes (≤2000), ≥1 baby (date_of_birth = delivery date, time `HH:MM`, optional sex/weight/length).
- **Expected:** One DB transaction: patient → DELIVERED (`delivery_date` set, `para+1`); `pregnancy_outcomes` row created/updated with `outcome_type=DELIVERED`, `confirmed_at=now()`, `confirmed_by=actor`, confirmation source/provenance; baby rows created. Redirect `patients.delivered` with success. Audit `UPDATE`/`PATIENT`. **Verify via SQL that patient, para, outcome, and babies committed together.**

### PAT-DEL-002 — Delivery validation & invariants
- **Steps:** (a) delivery in the future; (b) baby DOB ≠ delivery date; (c) no babies or baby missing date/time; (d) invalid location/source.
- **Expected:** DomainException surfaced as `status` error (e.g., "Baby date of birth must match the delivery date.", "At least one baby record is required."); nothing persisted; provenance fields are server-controlled.

### PAT-DEL-003 — Double delivery rejected
- **Steps:** After PAT-DEL-001, attempt to deliver the same patient again.
- **Expected:** Rejected ("already has a confirmed delivery outcome recorded." / "already marked as delivered"). `para` increments exactly once.

### PAT-DEL-004 — Delivered read-only & history pages
- **Steps:** Open delivered patient; use `GET /patients/delivered`, `/patients/delivered/{id}/history`, `/babies`, `/print-babies`.
- **Expected:** "Pregnancy Completed" banner; clinical write controls hidden; history groups all pregnancies of same identity; delivered list groups by identity (completed count, total babies, last delivery date, confirmed flag) with search by name/contact and pagination 10/page.

### PAT-DEL-005 — Legacy delivered without confirmed outcome
- **Steps:** Manually create a `DELIVERED` patient with no pregnancy outcome row (SQL).
- **Expected:** Monitoring state `LEGACY_DELIVERED` ("Historical Delivered Record"); shown as valid history, never auto-confirmed.

---

## EXPORT — Patient Download (PDF/CSV)

### EXPORT-001 — CSV download
- **Preconditions:** Complete patient record (all fields in `getPatientDownloadMissingFields` present).
- **Steps:** `POST /patients/{id}/download` with `format=csv`.
- **Expected:** `200`, `Content-Type: text/csv`, attachment disposition. Content has patient info, pregnancy info, medical history (18 conditions + other), latest visit, risk data; baby info added for delivered.

### EXPORT-002 — PDF download
- **Steps:** `POST /patients/{id}/download` with `format=pdf`.
- **Expected:** DomPDF letter-portrait PDF named `patient-{id}-record.pdf`; renders `exports/patient-record`.

### EXPORT-003 — Incomplete record blocked
- **Steps:** Clear one required field (e.g., LMP) on a patient; request download.
- **Expected:** `422` JSON `{"message":"Patient data is incomplete...","missing":["LMP"]}`; no file.

### EXPORT-004 — Format validation
- **Steps:** Submit `format=xlsx`.
- **Expected:** `422` validation error (`in:pdf,csv`).

---

## MED — Medical History

### MED-001 — Create medical history — happy path
- **Steps:** `GET /medical-histories/create?patient_id={id}`; check a few conditions (epilepsy, smoking); save.
- **Expected:** One record per patient (if a record exists, redirect to edit with info flash). Audit `CREATE`/`MEDICAL_HISTORY`. Unchecked checkboxes normalized to `false`.

### MED-002 — "Other" requires specify
- **Steps:** Check `other` but leave `other_specify` blank.
- **Expected:** Validation `other_specify required_if:other,1`; not saved.

### MED-003 — Delivered guard
- **Steps:** Try create/edit for a DELIVERED patient.
- **Expected:** Redirect with error "Medical history cannot be modified for a delivered patient."; no write.

### MED-004 — Recalculation trigger
- **Preconditions:** Patient has an `ASSESSMENT INCOMPLETE` visit and a missing Medical History.
- **Steps:** Create the Medical History.
- **Expected:** `recalculateIncompleteVisits` runs; if all three records now exist the incomplete visit is reassessed (see RISK-008). Finalized HIGH/LOW visits never rewritten.

### MED-005 — Visit-to-history diabetes/anemia sync (one-way)
- **Preconditions:** Medical History exists with diabetes=false; then save a prenatal visit with diabetes=1.
- **Steps:** Save the visit; inspect the Medical History row and audit logs.
- **Expected:** `MedicalHistory.diabetes` set to 1 via `MedicalHistoryConditionSyncService`; audit `MEDICAL_HISTORY_SYNC`. A false visit value must NOT clear a true history value (test both directions).

---

## BIRTH — Birth Plan

### BIRTH-001 — Create birth plan
- **Steps:** `GET /birth-plans/create?patient_id={id}`; fill required per `store` validation (deliver_in_clinic, transport_cost, saving_started, plan_more_children, knows_fp_method, used_fp_before) and optionals.
- **Expected:** Saved; redirect `patients.show`; audit `CREATE`/`BIRTH_PLAN`; recalculation triggered.

### BIRTH-002 — Edit/delete birth plan
- **Steps:** `GET/PUT /birth-plans/{id}`; `DELETE /birth-plans/{id}`.
- **Expected:** Update/delete persist (update validation requires numerics); audit `UPDATE`/`DELETE` `BIRTH_PLAN`; recalculation triggered on update.

---

## US — Ultrasound

### US-001 — Create ultrasound — happy path
- **Steps:** `GET /ultrasound/create/{patient}`; scan_date ≤ today; fetal_heartbeat ∈ [Normal 120-160, Tachycardia >160, Bradycardia <120, Weak, Absent]; fetal_movement ∈ [Active, Normal, Decreased, Absent]; presentation ∈ [Cephalic, Breech, Transverse, Oblique]; amniotic_fluid ∈ [Normal, Low, High, Moderate]; placenta_position; gestational_age_scan 4–42; estimated_fetal_weight 200–5000.
- **Expected:** Record created; audit `CREATE`/`ULTRASOUND`; recalculation triggered.

### US-002 — GA vs LMP consistency
- **Steps:** Enter gestational_age_scan differing from LMP-derived weeks by >3.
- **Expected:** Error "Gestational age doesn't match LMP date. Based on LMP (...), expected GA is about X weeks (±3 weeks allowed)."

### US-003 — EFW vs GA sanity check
- **Steps:** Enter EFW outside band `200+(GA-4)*80` … `200+(GA-4)*150` (e.g., GA=20 → ~1480–2600g).
- **Expected:** Error "Weight seems low/high for X weeks (expected around ...g)."

### US-004 — File handling
- **Steps:** (a) upload >5MB file; (b) upload .txt; (c) upload valid PDF; then update with a new file; then delete the ultrasound.
- **Expected:** (a) max error; (b) mimes error; (c) stored under `storage/app/public/ultrasounds/`; on update with a new file the old file is deleted from disk; on destroy the file is deleted.

### US-005 — Update validations
- **Steps:** Edit with future scan_date or out-of-range GA/EFW.
- **Expected:** Same validation as create; no persisted change.

---

## PRE — Prenatal Visit & Risk Assessment

### PRE-001 — Create visit — completeness gate
- **Preconditions:** Patient missing one of Medical History / Ultrasound / Birth Plan.
- **Steps:** Create a prenatal visit (BP 110/70, normal values).
- **Expected:** `risk_level='ASSESSMENT INCOMPLETE'`, `decision_source='COMPLETENESS'`, assessment "Assessment incomplete. The following required records are missing: …", recommendation lists missing records, `next_visit_date` = provided or +30 days, `missing_records` populated, BP verification `NOT_REQUIRED`.

### PRE-002 — Create visit — complete happy path
- **Preconditions:** All three records exist.
- **Steps:** Enter normal BP (110/70), weight, GA, no conditions; save.
- **Expected:** Visit persisted with risk from the engine (see RISK cases); `next_visit_date` = provided or computed; audit `CREATE`/`PRENATAL_VISIT`.

### PRE-003 — Validation bands
- **Steps:** Submit bp_sys <60/>200, bp_dia <40/>130, weight <30/>150, temp <35/>40, GA <4/>42, dilation <0/>10, future visit_date, past next_visit_date, bp_sys ≤ bp_dia.
- **Expected:** Custom messages (e.g., "Systolic BP must be at least 60 mmHg", "Visit date cannot be in the future", "Next visit date must be today or in the future"); BP logic error on both bp fields when sys ≤ dia.

### PRE-004 — Repeat BP both-or-neither + note
- **Steps:** Provide repeat_dia without repeat_sys; select `UNABLE_TO_REPEAT` without a note; provide both repeats.
- **Expected:** `required_with` errors; "A verification note is required when the status is …" ; with both repeats → `REPEAT_COMPLETED`. Note stored only when UNABLE_TO_REPEAT. Audit `BP_REPEAT_RECORDED`.

### PRE-005 — GA vs LMP on visit
- **Steps:** Enter GA differing >3 weeks from LMP-derived weeks.
- **Expected:** Error includes the LMP-derived expected weeks (±3 allowed).

### PRE-006 — Edit visit — recalculation + reminder reset
- **Preconditions:** Existing visit with `next_visit_date` set and reminder flags non-null.
- **Steps:** Edit BP/GA and also change `next_visit_date`.
- **Expected:** Risk reassessed; `reminder_tomorrow_sent_at`/`reminder_today_sent_at` cleared only when next visit date changed; audit `UPDATE`. Reassigning patient blocked: "The patient cannot be changed after a prenatal visit is recorded."

### PRE-007 — Editing initial BP clears stale repeat pair
- **Preconditions:** Visit has a repeat BP pair.
- **Steps:** Change only `bp_sys`/`bp_dia`; save.
- **Expected:** Repeat pair cleared (`repeat_bp_sys/dia = null`), audit `BP_INITIAL_EDITED`; verification recomputed. Unchanged initial BP with same-value prefilled repeat keeps original recorded_at/by provenance.

### PRE-008 — Reminder emails
- **Steps:** Create a visit with next_visit_date + patient email; then update next_visit_date later.
- **Expected:** Log lines `PRENATAL CREATE EMAIL SENT SUCCESSFULLY` / `PRENATAL UPDATE EMAIL SENT SUCCESSFULLY` (log mail driver); skipped lines when no email or no new date.

### PRE-009 — Delete visit
- **Steps:** Delete a visit.
- **Expected:** Soft-deleted; audit `DELETE`/`PRENATAL_VISIT`; disappears from lists and from latest-visit-per-patient subqueries.

---

## RISK — Risk Engine (deterministic rules + ML)

Precondition for RISK-001..006: all three required records exist (Medical History, Ultrasound, Birth Plan), and the ML step is reachable (python works) — otherwise results are recorded as `MACHINE_LEARNING_INVALID`.

### RISK-001 — Severe-range BP is pre-completeness HIGH+URGENT
- **Preconditions:** Complete records (also test the missing-records variant).
- **Steps:** Save visit with 170/100.
- **Expected:** `risk_level=HIGH`, `decision_source=RULE_BASED`, `urgency=URGENT_CLINICAL_REVIEW`, recommendation "Immediate qualified clinical review…", next visit +3 days. Missing records are preserved in `missing_records` when incomplete (PRIORITY 0 path).

### RISK-002 — Elevated BP (BP-H) on complete patient
- **Steps:** BP 145/95, complete records, no other factors.
- **Expected:** `risk_level=HIGH`, `decision_source=RULE_BASED`, `urgency=PROMPT`, reason "Elevated blood-pressure finding", next visit +3 days.

### RISK-003 — Each deterministic rule yields HIGH
- **Steps:** One patient at a time, clean BP (<140/90), complete records; set each factor and re-assess:
  - age < 19 (AGE-Y "Teenage pregnancy (under 19)")
  - age ≥ 35 AND gravida=1 AND para=0 (AGE-A "Advanced maternal age")
  - diabetes=1 (DM-01)
  - anemia=1 (AN-01)
  - previous_cs=1 (CS-01)
  - miscarriage ≥ 3 (RM-03)
  - ultrasound presentation Breech/Transverse/Oblique (US-P01)
  - ultrasound amniotic_fluid Low/High (US-AF01)
  - ultrasound fetal_heartbeat Weak/Abnormal/Absent (US-FH01)
- **Expected:** Each: `risk_level=HIGH`, `decision_source=RULE_BASED`, `rule_reasons`/`reasons` include the factor label, next visit +3 days, `factor_evidence` populated; labels deduplicated; assessment lists first 3 factors (+ "and N more factor(s).").

### RISK-004 — ML LOW only when complete + no rule HIGH
- **Preconditions:** Complete records; BP 110/70; age 25; gravida=2 para=1; no conditions; normal ultrasound; python functional.
- **Steps:** Save visit.
- **Expected:** `risk_level=LOW`, `decision_source=MACHINE_LEARNING`, `ml_prediction=LOW`, `ml_valid=true`, next visit +30 days, "Low-risk pregnancy…" assessment, routine-checkup recommendation.

### RISK-005 — ML HIGH
- **Steps:** Complete records; craft inputs the model classifies HIGH (e.g., older age, elevated-but-not-severe BP, diabetes, previous CS).
- **Expected:** `risk_level=HIGH`, `decision_source=MACHINE_LEARNING`, `ml_valid=true`, next visit +3 days. Exact boundary is exploratory — record the actual prediction.

### RISK-006 — ML unavailable → MACHINE_LEARNING_INVALID
- **Preconditions:** Python/`predict.py` fails (bad `PYTHON_PATH` or missing model), complete records, no rule triggers.
- **Steps:** Save visit.
- **Expected:** Raw output matched by error regex → `risk_level='ASSESSMENT INCOMPLETE'`, `decision_source='MACHINE_LEARNING_INVALID'`, `ml_prediction=null`, `ml_valid=false`, recommendation "Complete the missing record(s) before final risk classification." Restore `PYTHON_PATH` afterwards.

### RISK-007 — Reproducible context + data-quality + decision trace
- **Steps:** Inspect `assessment_metadata` (context, interaction_evidence, data_quality_flags, decision_trace, versions, assessed_at) and `factor_evidence` on any assessed visit.
- **Expected:** Context has deterministic latest ultrasound id/date, medical history/birth plan existence, sanitized visit inputs, patient static fields; duplicate MH/Birth Plan counts surface as data-quality flags; decision trace lists ordered steps; versions include current version map.

### RISK-008 — Recalculation only touches ASSESSMENT INCOMPLETE
- **Preconditions:** Patient with one finalized HIGH visit and one `ASSESSMENT INCOMPLETE` visit; all records complete.
- **Steps:** Update the Medical History (triggers recalculation).
- **Expected:** Only the incomplete visit is reassessed; finalized HIGH/LOW visits keep historical assessment/metadata. DELIVERED patients are skipped entirely.

---

## BP — Blood Pressure Verification (repeat workflow)

### BP-001 — Normal BP → NOT_REQUIRED
- **Steps:** BP 110/70, no repeats.
- **Expected:** `bp_verification_status='NOT_REQUIRED'`, `triggered=false`.

### BP-002 — Elevated initial, no repeat → PENDING_REPEAT
- **Steps:** BP 145/92, no repeat fields, no explicit status.
- **Expected:** BP-H, `verification_status='PENDING_REPEAT'`; Risk Monitoring lists it under Pending Repeat; no `BP_REPEAT_RECORDED` audit.

### BP-003 — Repeat completes verification
- **Steps:** BP 145/92 + repeat BP 135/88 (normal).
- **Expected:** `verification_status='REPEAT_COMPLETED'`, `repeat_interpretation='NORMAL'`; risk stays HIGH (BP-H); monthly analytics counts it as "cleared" (repeat <140/90).

### BP-004 — Repeat severe → BP-URG
- **Steps:** BP 145/92 initial, repeat 165/115.
- **Expected:** `isRepeatSevere` → BP-URG, `urgency='URGENT_CLINICAL_REVIEW'`, HIGH; label "Severe-range blood-pressure finding"; `effective_max_systolic/diastolic` = max of both readings; `repeat_interpretation='SEVERE'`.

### BP-005 — UNABLE_TO_REPEAT with note
- **Steps:** Elevated initial; set status `UNABLE_TO_REPEAT` with a note.
- **Expected:** `verification_status='UNABLE_TO_REPEAT'`, note stored in `bp_assessment.verification_note`; HIGH preserved; filtering by Unable to Repeat shows it.

### BP-006 — Urgency / verification filters (Risk Monitoring)
- **Preconditions:** One visit with BP-URG and one with BP-H/PENDING.
- **Steps:** In risk monitoring, filter Urgency = `URGENT_CLINICAL_REVIEW` and `PROMPT`; BP Verification = each status.
- **Expected:** Filters return matching latest visits; summary cards `urgentBpCount`/`pendingRepeatCount` reflect them.

---

## MON — Risk Monitoring Dashboard

### MON-001 — Latest visit per patient
- **Preconditions:** Patient with 2 visits (first LOW, later HIGH).
- **Steps:** `GET /risk-monitoring`.
- **Expected:** Only the latest visit (MAX(id) per patient, non-deleted) shown; HIGH count card reflects 1.

### MON-002 — Filters & search
- **Steps:** Apply risk_filter (HIGH/LOW/ASSESSMENT INCOMPLETE), decision_source (COMPLETENESS/RULE_BASED/MACHINE_LEARNING/MACHINE_LEARNING_INVALID), urgency, bp_verification_status; search by first/last/full name; press Clear.
- **Expected:** Valid values filter; invalid values ignored (whitelist); pagination 15/page; search matches `like '%x%'` on first/last/full name.

### MON-003 — Default ordering
- **Steps:** Open with no risk filter.
- **Expected:** Order by `CASE WHEN risk_level='HIGH' THEN 0 WHEN 'LOW' THEN 1 ELSE 2 END` (HIGH first). With an explicit risk filter applied, no risk-level ordering is used.

### MON-004 — Analytics endpoint
- **Steps:** `GET /risk-monitoring/analytics?month={1..12}`; also `month=all` and invalid values.
- **Expected:** JSON with `year`, `month`, `labels`, `highRiskTrend`, `riskDistribution`, `conditions`, `bpFollowUp`, `summary`; invalid/out-of-range month → All Months (null). Aggregated totals only, no patient PII.

### MON-005 — Patient scope
- **Steps:** Verify that only patients with status in [ONGOING, DELIVERED, REFERRED] appear.
- **Expected:** Other statuses excluded (only these are in use today).

---

## REF — Referrals

### REF-001 — Manual (legacy) referral — happy path
- **Preconditions:** ONGOING patient (not delivered).
- **Steps:** `GET /patients/{id}/referral/create` (no `prenatal_visit_id`); fill referred_to, reason, date_referred (required); store via `POST /referrals/store`.
- **Expected:** Referral created `status='Pending'`, `assessment_snapshot=null`, `created_by=auth id`. **Patient stays ONGOING** (no `REFERRED` status write). Audit `CREATE`/`REFERRAL`. Redirect referrals.index.

### REF-002 — Assessment-linked referral
- **Preconditions:** ONGOING patient with a HIGH visit carrying non-empty `assessment_metadata`.
- **Steps:** `GET /patients/{id}/referral/create?prenatal_visit_id={visitId}` → form shows snapshot-built reason prefill → store.
- **Expected:** Snapshot rebuilt server-side from persisted evidence (`ReferralAssessmentSnapshotService`, schema v1.0.0, no PII); `assessment_snapshot` stored; audit includes "linked to PrenatalVisit #".

### REF-003 — Linked-referral guards
- **Steps:** Attempt for: (a) DELIVERED patient; (b) non-existent visit; (c) visit of another patient; (d) visit not HIGH; (e) visit with empty `assessment_metadata`; (f) duplicate Pending referral on the same visit; (g) store after the visit is soft-deleted (TOCTOU).
- **Expected:** (a) "Delivered patients cannot be referred."; (b) 404; (c) 403 "does not belong to this patient"; (d) 403 "Referrals may only be created from HIGH-risk assessments."; (e) 422 manual-workflow message; (f) "A pending referral already exists for this assessment."; (g) store fails with the equivalent error. Nothing written on failure.

### REF-004 — Complete (follow-through)
- **Preconditions:** Pending referral on an ONGOING patient.
- **Steps:** `POST /referrals/{id}/complete`.
- **Expected:** Pending → Completed; `completed_at` set; refusal fields cleared; audit `UPDATE`. Retry or transition from a closed status → DomainException "Referral is already … and cannot transition to …".

### REF-005 — Refuse with waiver
- **Preconditions:** Pending referral on an ONGOING patient.
- **Steps:** `POST /referrals/{id}/refuse` with `refusal_notes` (≥10 chars) and `waiver_signed=1`.
- **Expected:** Pending → Refused; `refusal_recorded_at/by` server-stamped; `waiver_signed=1`; `completed_at=null`; audit appends waiver note. Notes <10 chars → validation error.

### REF-006 — Cancel
- **Steps:** `POST /referrals/{id}/cancel` on a Pending referral.
- **Expected:** Pending → Cancelled; original `referral.notes` preserved (never overwritten); refusal fields cleared; audit records the transition.

### REF-007 — Delivered patients are read-only for transitions
- **Preconditions:** Referral attached to a DELIVERED patient (SQL).
- **Steps:** complete / refuse / cancel.
- **Expected:** Redirect back with error "Delivered patients are read-only; referral status cannot be changed."

### REF-008 — Referral listing / analytics / detail / print
- **Steps:** `GET /referrals`, `/referrals/analytics?month=…`, `/referrals/{id}`, `/referrals/{id}/print`.
- **Expected:** Index paginated (15/page) with stats (Total/Pending/Completed/Refused/Cancelled), search by name, status filter, month filter; detail built from persisted snapshot (neutral fallback when creator/recorder deleted); print renders a printable letter; analytics JSON returns aggregate totals only.

---

## OUT — Pregnancy Outcome Monitoring (17D) + Follow-Up (17E)

**Derived state machine (never persisted):** RESOLVED / STILL_PREGNANT_CONFIRMED / UNABLE_TO_CONTACT / CONFIRMATION_REQUIRED / NOT_YET_DUE / LEGACY_DELIVERED / LEGACY_REFERRED / INVARIANT_VIOLATION.

### OUT-001 — Page render & stats
- **Preconditions:** Mix of patients: some EDD passed unconfirmed, one EDD in future, one delivered confirmed, one legacy delivered.
- **Steps:** `GET /pregnancy-outcomes`.
- **Expected:** 4 stat cards (Action Required / Still Pregnant / Unable to Contact / Confirmed Deliveries); queue ordered by STATE_ORDER (confirmation-required first, then days-until EDD); friendly labels only (no raw enums); search by first/middle/last name; state filter slugs (confirmation-required, still-pregnant, unable-to-contact, resolved); pagination 15/page.

### OUT-002 — Derivation cases
- **Steps:** Using SQL/Carbon to set scenarios, verify each row's badge and text:
  - ONGOING, EDD future or null → NOT_YET_DUE "Monitoring Not Yet Due".
  - ONGOING, today > EDD, no outcome/follow-up → CONFIRMATION_REQUIRED "Outcome Confirmation Required".
  - ONGOING, today > EDD, follow-up STILL_PREGNANT_CONFIRMED within 7 days → STILL_PREGNANT_CONFIRMED "Still Pregnant — Confirmed" (falls back to CONFIRMATION_REQUIRED after the window).
  - ONGOING, today > EDD, follow-up UNABLE_TO_CONTACT within 7 days → UNABLE_TO_CONTACT "Unable to Contact".
  - ONGOING but a confirmed outcome row exists → INVARIANT_VIOLATION "Outcome Data Invariant Violation — Requires Review" (never auto-rewritten).
  - DELIVERED + confirmed outcome → RESOLVED "Confirmed Delivery".
  - DELIVERED without outcome → LEGACY_DELIVERED "Historical Delivered Record".
  - REFERRED status → LEGACY_REFERRED "Legacy Referred Record".
- **Expected:** All eight derived states renderable; EDD cell shows "X days until EDD" or "X days past EDD".

### OUT-003 — Follow-up buttons only for eligible non-admin staff
- **Preconditions:** Eligible ONGOING patient (status ONGOING, no confirmed outcome, EDD not null, today > EDD).
- **Steps:** View monitoring index as staff and as admin.
- **Expected:** Staff sees green "Confirm Still Pregnant" + rose "Unable to Contact" buttons (desktop table and mobile cards). Admin sees NO follow-up buttons. Modal component present only when `$hasFollowUpRows && role !== 'admin'`.

### OUT-004 — Record "Still Pregnant"
- **Steps:** Staff clicks Confirm Still Pregnant → modal → Confirm → `POST /patients/{id}/pregnancy-outcome/still-pregnant`.
- **Expected:** `follow_up_status='STILL_PREGNANT_CONFIRMED'`, `follow_up_recorded_at=now()`, `follow_up_recorded_by=actor` (server-controlled); patient stays ONGOING; redirect success; audit `UPDATE`/`PATIENT`.

### OUT-005 — Record "Unable to Contact"
- **Steps:** `POST /patients/{id}/pregnancy-outcome/unable-to-contact`.
- **Expected:** `follow_up_status='UNABLE_TO_CONTACT'`; recorded provenance; stays ONGOING; audit.

### OUT-006 — Follow-up eligibility guards (service-level)
- **Steps:** Attempt on: (a) NOT_YET_DUE (EDD today/future/null); (b) DELIVERED; (c) legacy REFERRED; (d) ONGOING with confirmed outcome; (e) direct POST with a stale model after another request completed.
- **Expected:** DomainException messages (e.g., "Follow-up observations are only accepted once the expected delivery date has passed.", "A delivered pregnancy can no longer record follow-up observations."); no write occurs. `isFollowUpEligible` requires ALL: ONGOING + no confirmed outcome + EDD not null + today strictly after EDD.

### OUT-007 — Follow-up recency expiry
- **Preconditions:** A STILL_PREGNANT_CONFIRMED recorded 8 days ago.
- **Steps:** Open the monitoring page.
- **Expected:** Row returns to CONFIRMATION_REQUIRED (7-day window inclusive of day 7; expires on day 8). Exactly 7 days old → still within window.

### OUT-008 — Invariant violation on page
- **Steps:** Create an ONGOING patient with a confirmed outcome row (SQL); open monitoring.
- **Expected:** Badge INVARIANT_VIOLATION; `status` not auto-fixed; row listed for manual review.

---

## FUP — Follow-Up Confirmation Modal (17E UI/UX)

### FUP-001 — Open & content
- **Preconditions:** Eligible row visible to staff.
- **Steps:** Click a follow-up button.
- **Expected:** Modal opens (no submission); title, message, patient name, and confirm label populated from `data-*` attributes; tone applied (green for confirm, rose for alert); `form.action` = correct routed POST; focus moves into the dialog.

### FUP-002 — Cancel / backdrop / Escape
- **Steps:** Open; press Cancel; reopen and click the backdrop; reopen and press Escape.
- **Expected:** Each closes without submitting; focus returns to the originating button.

### FUP-003 — Keyboard focus trap
- **Steps:** Open; Tab and Shift+Tab repeatedly.
- **Expected:** Focus cycles between Cancel and Confirm (Tab from last → first; Shift+Tab from first → last); Tab while closed has no effect.

### FUP-004 — Submit state
- **Steps:** Click Confirm.
- **Expected:** Button disabled, `aria-busy=true`, spinner visible, label "Saving..."; duplicate submissions blocked; page navigates on success.

### FUP-005 — Modal present only when eligible + role
- **Steps:** As staff compare: (a) monitoring index with ≥1 eligible row → modal present; (b) monitoring index with zero eligible rows → no modal; (c) patient show → modal present only when `status===ONGOING && monitoringEligible && role!==admin`. As admin: none render the modal.

### FUP-006 — Row reflects recorded follow-up
- **Steps:** After recording a follow-up, re-open monitoring.
- **Expected:** The row shows the derived state badge (STILL_PREGNANT_CONFIRMED / UNABLE_TO_CONTACT) and "recorded by {staff name}"; buttons remain consistent with eligibility.

---

## UI — Responsive & Accessibility Spot Checks

### UI-001 — Monitoring page desktop vs mobile
- **Steps:** View `GET /pregnancy-outcomes` at ≥1024px then ≤1024px.
- **Expected:** Desktop table (hidden lg:block); mobile cards (lg:hidden). Follow-up buttons available in both layouts for eligible staff.

### UI-002 — Modal dialog semantics
- **Steps:** Open the modal; inspect DOM.
- **Expected:** `role="dialog"`, `aria-modal="true"`, `aria-labelledby`/`aria-describedby` point to title/message IDs; focus lands in dialog; Escape closes; single shared modal instance (`data-outcome-modal-initialised` guard).

### UI-003 — Badge tone consistency
- **Steps:** Compare patient show page, monitoring page for the same patient.
- **Expected:** Same monitoring badstate color classes (amber/green/rose/slate) from the shared match logic in the controller row builder; labels identical.

---

---

## 5. Totals by Module

| Section | ID range | Count |
|---------|----------|-------|
| AUTH | AUTH-001…006 | 6 |
| PROFILE | PROFILE-001…003 | 3 |
| SEC | SEC-001…005 | 5 |
| PAT | PAT-001…009 | 9 |
| PAT-DEL | PAT-DEL-001…005 | 5 |
| EXPORT | EXPORT-001…004 | 4 |
| MED | MED-001…005 | 5 |
| BIRTH | BIRTH-001…002 | 2 |
| US | US-001…005 | 5 |
| PRE | PRE-001…009 | 9 |
| RISK | RISK-001…008 | 8 |
| BP | BP-001…006 | 6 |
| MON | MON-001…005 | 5 |
| REF | REF-001…008 | 8 |
| OUT | OUT-001…008 | 8 |
| FUP | FUP-001…006 | 6 |
| UI | UI-001…003 | 3 |

**Total test cases: 97**

**Priority split (suggested):**
- **Critical (patient-safety / data-integrity):** RISK-001, RISK-002, RISK-003, RISK-006, RISK-007, RISK-008, PAT-DEL-001/-002/-003, OUT-004/-005/-006/-007, REF-003, PRE-001, PRE-007, EXPORT-003, SEC-001 = **18 critical**.
- **High:** Otherwise (validation, filters, role guards) ≈ **36 high**.
- **Medium/Low (UI/UX polish):** FUP-001…006, UI-001…003, AUTH-004, PROFILE = **rest (~43)**.

## 6. End-to-End (E2E) Scenarios

### E2E-1 — Full ONGOING journey (staff)
1. Login as staff (AUTH-001).
2. Create patient (PAT-002) → add Medical History (MED-001) → Birth Plan (BIRTH-001) → Ultrasound (US-001).
3. Create prenatal visit with 110/70 → expect LOW / MACHINE_LEARNING (PRE-002, RISK-004).
4. Create second visit with 145/95 → expect HIGH / RULE_BASED + PENDING_REPEAT (RISK-002, BP-002).
5. Record a repeat BP on edit → REPEAT_COMPLETED (BP-003).
6. Open Risk Monitoring → the patient shows one latest HIGH row (MON-001).
7. Create an assessment-linked referral from the HIGH visit (REF-002) → mark Completed (REF-004).
8. Force EDD to yesterday (SQL) → **pregnancy-outcomes** page shows CONFIRMATION_REQUIRED (OUT-002).
9. Record "Still Pregnant" via modal (OUT-004, FUP-004) → badge STILL_PREGNANT_CONFIRMED.
10. Confirm delivery with one baby (PAT-DEL-001) → patient DELIVERED; monitoring shows RESOLVED; delivered list shows the pregnancy.

### E2E-2 — Incomplete lifecycle + auto-recalculation
1. Create patient, then a prenatal visit before any clinical records → ASSESSMENT INCOMPLETE / COMPLETENESS (PRE-001).
2. Add Medical History, Ultrasound, Birth Plan → the incomplete visit auto-recalculates (MED-004).
3. With no risk factors → LOW MACHINE_LEARNING (RISK-004).

### E2E-3 — Access control sweep
1. As admin attempt the full write matrix (SEC-001) → all 403.
2. As staff attempt staff CRUD + audit logs (SEC-002/-003) → 403.
3. Confirm read pages open for both roles (SEC-004).

### E2E-4 — Export integrity
1. Complete patient → PDF + CSV download (EXPORT-001/-002).
2. Remove LMP → both blocked with `missing:["LMP"]` (EXPORT-003).

## 7. Key Business Rules (authoritative — from code)

1. **Follow-up eligibility** (`PregnancyOutcomeMonitoringService::isFollowUpEligible` / `isEddPassed`): accepted only when patient `ONGOING`, **no confirmed outcome**, EDD not null, and **today strictly after EDD** (EDD today ⇒ NOT_YET_DUE). Follow-up window: 7 days inclusive.
2. **EDD passed ≠ DELIVERED**: only the Sprint 17C confirmed-delivery workflow writes DELIVERED; monitoring never auto-confirms.
3. **Passing the EDD ⇒ nothing clinical changes**; it only opens the confirmation window.
4. **Severe BP (≥160/110) is a Priority-0 safety gate** that resolves to HIGH + URGENT_CLINICAL_REVIEW even when records are missing (missing records preserved).
5. **ML is evaluated only when complete AND no deterministic HIGH factor**; failed ML ⇒ MACHINE_LEARNING_INVALID (risk ASSESSMENT INCOMPLETE).
6. **Recalculation only rewrites ASSESSMENT INCOMPLETE visits**; finalized HIGH/LOW are historical; DELIVERED patients skipped.
7. **Editing the initial BP clears the stale repeat pair**; unchanged initial BP preserves repeat provenance.
8. **Referral status is decoupled from lifecycle**: creating a referral leaves the patient ONGOING; transitions Pending→Completed/Refused/Cancelled are terminal.
9. **Referral transfers are read-only for DELIVERED patients**.
10. **One confirmed delivery per pregnancy**: double delivery rejected; `para` increments exactly once.
11. **Medical History sync is one-way + monotonic**: true visit values may set history true; false values never clear a true history.
12. **Baby records on DELIVERED/REFERRED pregnancies are immutable** (backend 403).

## 8. UI-Unverifiable-Automatically / Manual-Only Items

- Modal focus-trap behaviour, focus return, and Escape/backdrop handling (FUP-002/-003).
- Responsive breakpoint rendering of the monitoring page and patient profile (UI-001).
- PDF layout/print fidelity (EXPORT-002, REF-008 print).
- Actual email delivery text in an inbox (PRE-008 relies on log driver).

## 9. Missing / Unclear implementations (do NOT test as existing)

- **No 17F implementation or docs** exist in the repo; do not expect 17F features.
- **Outcome types beyond DELIVERED** (miscarriage, stillbirth, etc.) are explicitly deferred in `PregnancyOutcomeVocabulary`; the delivery form only offers DELIVERED workflows.
- **`assigned_staff_id` has no data-scoping** beyond the "My patients" filter; there is no staff-level read isolation.
- **Registration default role** is not asserted by any test — verify the migration default before testing route access for new registrants (AUTH-004).
- **Approval "Urgent" reason-codes** for AGE factors and CS/RM are labeled REVIEW_REQUIRED in the registry, but the *urgency* column persisted on visits is only populated for BP findings (BP-URG/BP-H); do not expect AGE/RM/CS to set `urgency`.
- ML HIGH/LOW decision boundaries are black-box (Random Forest); RISK-005 is exploratory.

## 10. Suggested Sign-off

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Test Lead / Developer | | | |
| Clinical Reviewer | | | |
| Admin / Approver | | | |

---

*End of manual testing / UAT case document. All test cases reference verified repository behaviour; empty Actual Result / Pass–Fail / Remarks columns are intentional and filled during execution.*