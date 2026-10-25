# Implementation Progress

## Automatic patient portal account setup

- Patient registration now requires a valid email, trims and lower-cases it, and provisions the portal account automatically after the patient record is saved.
- Portal account creation continues to use the existing transactional `PatientAccountCreationService`; setup email delivery remains synchronous and outside that transaction.
- Extracted shared setup-email sending and safe failure auditing into `PatientAccountSetupEmailService`, used by automatic registration and the existing account creation/resend controller. Email failures retain the patient and pending account and direct staff to the existing resend action.
- Removed the manual Create Patient Account button and modal from Patient Profile. Account status, archived-account information, and the pending-account resend action remain. The underlying account route remains available for compatibility.
- Added registration workflow coverage for required/normalized email, automatic provisioning, duplicate email protection, repeated submissions, mail failure/resend recovery, and unexpected provisioning failure; updated Patient Profile UI coverage.
- PHP syntax checks and `git diff --check` pass. Feature tests were attempted, but this execution environment reports the Windows workspace path as nonexistent to Laravel's subprocess runner; direct Pest execution consequently did not load the project's `tests/Pest.php` feature-test base and failed before assertions or database work.

### Defense notes

- The patient record is persisted before account provisioning so account conflicts or unexpected provisioning failures cannot discard the clinical registration. Existing account creation remains atomic for user, patient pointer, verified ownership links, and audit data.
- The globally unique user email plus service-level patient lock prevents duplicate portal accounts. A repeated submission may create another patient registration row, but cannot attach an existing portal account or send another setup message for that email.
- Setup token format, 24-hour expiration, single-use consumption, and portal activation behavior are unchanged. Mail is not sent in a database transaction; a failed send is reported as a warning while the pending account remains eligible for a later resend.

## Appointment email reminders

- Patient-portal appointment creation now sends an immediate receipt email after the booking commits. Auto-confirmed checkups receive confirmed wording; other appointment types receive request-received wording that clearly says the schedule is pending review.
- Added `appointments:send-reminders` for confirmed appointments that are not linked to prenatal visits. Existing prenatal reminder behavior remains the owner for linked follow-ups.
- Sends one-week, three-day, and two-hour reminders independently from the current confirmed date and time. Schedule calculations use the appointment workflow's Asia/Manila timezone and a five-minute scheduler grace window; expired windows are recorded as missed.
- Added schedule-keyed tracking so each reminder type can be sent once per confirmed schedule. A reschedule creates a new key; failed delivery removes its claim for retry.
- Registered the command every minute with overlap protection. No deployment configuration or production database was changed.
- Added focused feature tests. Execution is currently blocked before test assertions by duplicate `babies.deleted_at` migrations in the working tree (`2026_10_18_000001_add_soft_deletes_to_babies_table.php` and `2026_10_18_000003_add_deleted_at_to_babies_table.php`). The test database remains SQLite `:memory:`; neither migration nor tests touched MySQL.

### Defense notes

- Separate tracking rows preserve independent delivery and retry state for each reminder type. Including the confirmed date and time in the unique key prevents an old schedule's sent state from suppressing reminders after rescheduling.
- Prenatal-linked appointments are excluded to avoid duplicating the existing prenatal Next Visit reminder flow.

## Routine appointment booking scope and patient service announcement

- New patient requests and staff walk-ins now accept checkups only. Both FormRequests validate against `Appointment::ROUTINE_BOOKABLE_TYPES`, and `AppointmentManagementService` enforces the same restriction at its write boundary.
- Removed counseling and delivery choices from the staff calendar Service Type filter and removed patient-facing copy that advertised them as bookable appointment options. Legacy-type filter URLs safely fall back to All Types so old appointments remain visible.
- Kept the existing counseling and delivery type constants, labels, and historical management/confirmation behavior so previously stored records remain viewable and actionable. Prenatal follow-up appointments continue to be created as checkups through their existing dedicated service.
- Added an Other Services and Announcements dashboard card linking to the clinic Facebook page in a new tab with `noopener noreferrer`; the text directs patients to check current announcements and availability without promising a schedule or booking channel.
- Updated appointment feature coverage for rejected new counseling/delivery bookings, checkup-only UI behavior, and the dashboard link. Reminder services, email notifications, schema, and migrations were left untouched.

### Defense notes

- `Appointment::TYPES` still describes values that may exist in stored history; the new `ROUTINE_BOOKABLE_TYPES` explicitly defines current booking policy. This avoids breaking calendar filters, historical records, and legacy lifecycle transitions.
- The request validation gives staff/patients field-level feedback, while the service guard prevents direct callers from bypassing the policy. Prenatal follow-up creation remains outside these routine booking entry points.
- The Facebook card is informational only. It does not claim all mentioned services are currently offered or available on a particular day.
