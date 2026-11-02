# Audit logging coverage checklist

This checklist records the audit-worthy write workflows reviewed for the
central `audit_logs` table. Technical delivery state and read-state changes are
listed separately so they are not confused with clinical or administrative
transactions.

## Architecture and guarantees

- `App\Models\AuditLog` remains the single audit record model and table.
- `App\Http\Controllers\Controller::logAction()` is the shared controller
  helper. Services that already own transactional workflows write through
  `AuditLog::create()` in that transaction.
- Audit entries preserve the actor name at write time and identify the affected
  record with `record_type` and `record_id`. `user_id` may be null for automated
  work; automated entries identify the process in `actor_name`.
- No passwords, reset tokens, access tokens, or unnecessary patient details
  belong in descriptions.
- Audit listing is admin-only on the server, newest-first, paginated, and
  filterable by actions/modules found in the log table. `RESTORE` is available
  even before the first restore is logged.

## Mutation coverage

| Workflow / reviewed write | Audit event and target | Source of mutation / audit | Automated coverage |
|---|---|---|---|
| Patient registration | `CREATE` / `PATIENT` | `PatientController`; portal-account creation is a separate `PATIENT_ACCOUNT_CREATED` event | `AutomaticPatientPortalRegistrationTest`, `PatientPhilhealthTest` |
| Patient information and baby-record edits | `UPDATE` / `PATIENT` or `BABY` | `PatientController` | `PatientPhilhealthTest`; patient profile/record feature tests |
| Patient archive and cascade restore | `DELETE`, `RESTORE` / `PATIENT`; one parent event represents the cascade | `PatientController`, transaction encloses restore and its audit | `PatientArchiveRestoreLifecycleTest` (including rollback and one-event checks) |
| Prenatal visit create, update, archive, restore, BP corrections, history synchronization | `CREATE`, `UPDATE`, `ARCHIVE`, `RESTORE`, `BP_*`, `MEDICAL_HISTORY_SYNC` / visit or history record | `PrenatalVisitController` | `PrenatalVisitArchiveWorkflowTest`, `Sprint10BloodPressureCorrectionsTest`, `PrenatalVisitConditionSyncTest`, `MedicalHistoryConditionSyncServiceTest` |
| Medical history create/update | `CREATE`, `UPDATE` / `MEDICAL_HISTORY` | `MedicalHistoryController` | `MedicalHistoryScopeTest`, `PrenatalVisitConditionSyncTest`, `MedicalHistoryConditionSyncServiceTest` |
| Birth plan create/update/delete | `CREATE`, `UPDATE`, `DELETE` / `BIRTH_PLAN` | `BirthPlanController`; recalculation remains container-resolved | `BirthPlanAuditLoggingTest` |
| Ultrasound create/update/delete | `CREATE`, `UPDATE`, `DELETE` / `ULTRASOUND` | `UltrasoundController`; recalculation remains container-resolved | `UltrasoundFetalAssessmentsTest` (create assertion plus upload validations) |
| Referral creation, editing, completion/refusal/cancel, archive/restore | `CREATE`, `UPDATE`, `ARCHIVE`, `RESTORE` / `REFERRAL` | `ReferralController` | `AssessmentLinkedReferralTest`, `ReferralFollowThroughTest`, `ReferralArchiveWorkflowTest` |
| Appointment request, walk-in, confirmation/decline/cancel/completion, patient change requests and staff reviews, prenatal follow-ups | Specific `APPOINTMENT_*`, `*_REQUEST_*`, and `WALK_IN_*` events / `APPOINTMENT` | `AppointmentManagementService`, `PrenatalFollowUpAppointmentService`; lifecycle audit is written with the transaction | `Phase3B2BAppointmentManagementTest`, `Phase3B3PatientAppointmentsTest`, `AppointmentCompletionDateRestrictionTest`, `Phase3D2CAppointmentChangeRequestReviewTest`, `Phase3D3B2StaffRescheduleReviewTest` |
| Clinic unavailability creation, edit, removal | `CLINIC_UNAVAILABILITY_*` / `CLINIC_UNAVAILABILITY` | `ClinicAvailabilityService` | `ClinicClosureManagementTest`, `AdminClinicAvailabilityManagementTest` |
| Pregnancy outcome and follow-up observations | `UPDATE` / `PATIENT` | `PatientController`, `PregnancyOutcomeController`, `PregnancyOutcomeRecordingService` | `PregnancyOutcomeRecordingTest`, `PregnancyOutcomeFollowUpTest` |
| Pregnancy continuation and verified history-link creation/reconciliation | `CREATE` / `PATIENT`; `PREGNANCY_HISTORY_LINKED` / `USER_PREGNANCY_LINK`; console reconciliation records a system actor | `PregnancyContinuationService`, `PregnancyHistoryAssociationService`, `ReconcilePregnancyHistoryLinks` | `Phase3H3PregnancyContinuationTest`, `Phase3HTaskBAutomaticHistoryAssociationTest` |
| Patient-account creation, setup, resend/failure, initial/password reset, and account lifecycle | Specific `PATIENT_*`, `PASSWORD_*`, `ARCHIVE`, `RESTORE` events / `USER`, `PATIENT`, or `STAFF` | Account services and profile/auth/admin controllers | `Phase2BPatientAccountCreationTest`, `Phase2CInitialPasswordChangeTest`, `Phase2Sprint1PatientAccountManagementTest`, `Phase2Sprint2AccountLifecycleTest`, `Phase15PatientAccountSetupEmailTest`, `StaffAccountLifecycleBatchTwoTest`, `StaffAccountPasswordResetTest`, `StaffAccountAdministrationBoundaryTest`, `PasswordUpdateTest` |
| Staff profile/role/account changes | `CREATE`, `UPDATE`, `ARCHIVE`, `RESTORE`, and password-request events / `STAFF` | `StaffController`, account-management controllers | `StaffProfileBatchOneTest`, `StaffAccountLifecycleBatchTwoTest`, `StaffAccountAdministrationBoundaryTest`, `StaffAccountPasswordResetTest` |
| User profile/photo/password and reset-link password completion | `UPDATE`, `PASSWORD_CHANGED`, `PASSWORD_RESET` / `USER` | `ProfileController`, password controllers; reset audit is in the password-update transaction | `PasswordUpdateTest`, `PasswordResetTest` |

`UltrasoundController::destroy()` writes a DELETE event, but the current web
routes do not map an ultrasound DELETE endpoint. Ultrasound deletion is
therefore not a presently available user workflow; patient cascade archival
is represented by the parent patient's archive event.

## Reviewed writes intentionally not recorded as separate business events

- Notification `read_at` changes are user read-state, not clinical or workflow
  changes.
- Appointment reminder claim/delivery rows and reminder timestamps support
  idempotent delivery/retry; they do not represent a clinical decision or
  appointment lifecycle transition. Appointment creation, confirmation, and
  rescheduling themselves are audited.
- Cache/session/temporary-file writes, analytics reads, migrations, seeds, and
  test fixtures are not user-facing business mutations.
- No administrator UI for editing risk thresholds/model configuration was
  found. Risk results remain attributable through the audited prenatal-visit
  create/update that stores the clinical inputs and recalculated result; the
  clinical thresholds/model were not changed.

## Page and access coverage

- `RESTORE`, common actions, and all distinct action/module values already
  present are available as filter choices.
- Filtering is validated against available values; pagination retains the
  filters and sorts by timestamp then ID descending.
- Authorization is checked in `AuditLogController`, not only in the Blade
  page.
- `PatientArchiveRestoreLifecycleTest` exercises RESTORE filtering,
  pagination/query-string retention, and staff denial.

## Remaining verification boundary

The checklist reflects the application's current code paths and test suites.
It does not claim that every possible database write in third-party packages,
future code, or direct production SQL is intercepted. New business mutations
must add an audit event and an assertion to the matching feature test.
