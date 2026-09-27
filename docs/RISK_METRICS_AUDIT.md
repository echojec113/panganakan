# Risk metric audit and verification (2026-09-27)

## Audit before changes

Routes: `dashboard` invokes DashboardController::index (staffDashboard for staff,
adminDashboard for admins); `patients.index` invokes PatientController::index;
`prenatal-visits.index` invokes PrenatalVisitController::index.

| Display | Exact original producer / Blade binding | Unit and filters | Assessment |
|---|---|---|---|
| Staff High Risk | DashboardController::staffDashboard, staffHighRiskCount; dashboards/staff.blade.php | PrenatalVisit whereIn latestVisitSubquery IDs, risk_level HIGH; all patient statuses | Correct; latest date then ID already fixed before this task |
| Staff Low Risk | Same, staffLowRiskCount | Same, risk_level LOW | Correct |
| Priority Alerts | Same, highRiskAlerts; Blade count(highRiskAlerts) | Latest IDs from priorityAlertLatestVisitSubquery, HIGH, whereHas patient status ONGOING; no limit | Correct |
| High-Risk Patients list | Same highRiskAlerts, Blade foreach | Same population as Priority Alerts; ordered visit_date DESC, id DESC | Correct for existing ongoing-patient subtitle |
| Follow-Up Attention / Overdue Follow-Ups | Same, followUpOverdueCount | Latest IDs, non-null next_visit_date, whereDate before today, whereHas ONGOING patient; count without limit | Correct |
| Admin overdue total | DashboardController::adminDashboard, overdueCount | Same ongoing/latest/overdue population; take(5), get(), then collection count | Incorrect total; variable passed to admin view, currently not rendered there |
| Patient Records Total Patients | PatientController::index, patients; patients/index.blade.php count | Patient status ONGOING; optional filter=my adds assigned_staff_id=auth ID | Correct active-list total; original all-registered subtitle misleading |
| Patient Records High-Risk Cases | patients/index.blade.php filters patients using prenatalVisits where risk_level HIGH count > 0 | Listed ongoing patients with ANY historical HIGH visit | Historical count, ambiguous for current monitoring; changed to latest HIGH |
| Prenatal Total Visits | PrenatalVisitController::index, visits; prenatal_visits/index.blade.php count | All visits whose patient is ONGOING | Correct under existing active-list requirement |
| Prenatal High Risk | Same collection where risk_level HIGH count | HIGH visits, not unique patients | Correct |
| Prenatal Low Risk | Same collection where risk_level LOW count | LOW visits, not unique patients | Correct |
| Prenatal This Month | Same collection where visit_date >= startOfMonth | Active visits; lacked upper date bound | Could include later months/years |

All visit queries above exclude soft-deleted visits. Both models use SoftDeletes;
Patient relationships inherit visit deletion filtering. Patient-based queries and
whereHas(patient) exclude deleted patients. Dashboard all-status latest counts do
not separately require a live parent; normal Patient deletion cascades to visits.
The live database has zero non-deleted visits without a live parent. No extra
global scope, alternate model connection, risk accessor, cache, or multiplying join
supplies these cards. Patient risk_level was removed by a migration and is not read
by these metrics. RiskAssessmentService writes visit assessments but is not invoked
to compute these index counts. No recalculation was performed.

Patient::latestPrenatalAssessment uses ofMany(visit_date max, id max) and excludes
deleted visits. Dashboard's existing NOT EXISTS definition agreed. However,
RiskMonitoringDataService::latestVisitSubquery, RiskMonitoringController's helper,
and RiskAnalyticsService::ageDistribution/latestAssessments used MAX(id). The
embedded monitoring table could therefore disagree with dashboard cards after a
backfill. Analytics joins one patient by primary key; filters year/month AFTER
selecting the latest assessment overall. This period-filter behavior is preserved.
Analytics and table request filters do not filter top dashboard cards. Client-side
listing search/risk filters do not recalculate the summary cards.

## Findings and canonical semantics

The observed numbers were reproduced: 48 non-deleted visits clinic-wide, including
24 HIGH, versus 24 visits for ongoing patients, including 8 HIGH. Latest-HIGH
patients total 16: 6 ONGOING, 9 DELIVERED, 1 REFERRED. Thus the apparent 16 > 8
contradiction compared different populations; 16 <= 24 holds clinic-wide.

Keep the explicitly tested ongoing-only prenatal listing (see
PrenatalVisitsActiveListingTest), including its visit-level cards. Patient Records
also remains ongoing-only and honors My Patients. Its High-Risk Cases now means
latest HIGH, making it a current-monitoring metric rather than lifetime history.
Without My Patients it matches Priority Alerts. Dashboard High/Low remain all-status
latest assessments; the action list and alerts deliberately include ongoing only.
Patients with no visits contribute to Patient Records total but not risk counts.
Patient records represent pregnancy records, not deduplicated people across pregnancies.

## Exact changes and design decisions

- app/Models/PrenatalVisit.php: added latestAssessmentIds, moving the already-correct
  anti-existence query into a shared model query helper. Greatest clinical date,
  then ID, excluding deleted candidates and competitors. Non-null visit_date is
  required by the schema. Existing equivalent Patient relationship remains intact.
- app/Http/Controllers/DashboardController.php: latest helper delegates to the shared
  query; removed duplicate priority helper; overdue total counts a cloned unrestricted
  query before selecting the five displayed rows.
- app/Services/RiskMonitoringDataService.php and
  app/Http/Controllers/RiskMonitoringController.php: MAX(id) replaced by shared IDs.
- app/Services/RiskAnalyticsService.php: both MAX(id) selectors replaced by shared
  IDs; corrected comment to describe existing latest-overall-then-period-filter behavior.
- app/Http/Controllers/PatientController.php: cloned active/assigned query counts
  whereHas(latestPrenatalAssessment, HIGH); passes highRiskCount to view.
- resources/views/patients/index.blade.php: uses highRiskCount instead of loading
  every historical visit per patient; clarifies ongoing/latest scope.
- resources/views/prenatal_visits/index.blade.php: adds exclusive start-of-next-month
  bound; clarifies ongoing scope. Controller/listing query unchanged.
- resources/views/dashboards/staff.blade.php: subtitle-only scope clarification.
- tests/Feature/RiskMetricSemanticsTest.php: isolated database regression coverage.
- docs/verify-risk-metrics.php: read-only independent sorted-visit oracle plus grouped SQL.
- docs/RISK_METRICS_AUDIT.md and docs/IMPLEMENTATION_PROGRESS.md: audit and defense notes.

No clinical thresholds, historical risk values, records, routes, or layout changed.
Pre-existing workspace changes were retained.

## Verification

Run `php artisan tinker`, then:

```php
require base_path('docs/verify-risk-metrics.php');
```

The script independently sorts visits by date/ID, selects one per patient, prints
all requested counts (including null/unexpected risks), and compares IDs with both
the shared query and Patient relationship. It does not write database records.
For a direct grouped SQL check in Tinker:

```php
App\Models\PrenatalVisit::selectRaw('risk_level, COUNT(*) AS total')->groupBy('risk_level')->get();
```

Observed after changes:

| Population | Total | HIGH | LOW | INCOMPLETE | Unexpected |
|---|---:|---:|---:|---:|---:|
| All non-deleted visits | 48 | 24 | 12 | 11 | 1 |
| Latest assessments, all statuses | 32 | 16 | 6 | 9 | 1 |
| Latest assessments, ongoing | 15 | 6 | 4 | 4 | 1 |
| Prenatal page visits, ongoing | 24 | 8 | 9 | 6 | 1 |

Patient Records total 45; current High-Risk Cases 6 (previously ever-HIGH 7).
Priority Alerts/list 6; staff overdue 15; admin overdue total 15 with 5 list rows
(previous total 5). Prenatal This Month 3. Both independent ID comparisons passed.
Unexpected stored risk: `THE SYSTEM CANNOT FIND THE PATH SPECIFIED.` (one record);
no null group currently. Left untouched; diagnosis of its original write is outside
this query-only task. HIGH/LOW/INCOMPLETE alone therefore do not cover every visit.

`php vendor/bin/phpunit tests/Feature/RiskMetricSemanticsTest.php --do-not-cache-result`
passed: 3 tests, 29 assertions, SQLite in-memory. Covers backfill ordering, same-day
ties, deleted latest visits, relation/helper/table/analytics/card agreement, assigned
staff and lifecycle scopes, archived patients, overdue above five, today/null return
dates, repeated visits and month/year boundaries. New tests use PHPUnit explicitly
because the existing Pest runner failed to bind Tests\TestCase on this Windows setup;
the attempted existing PrenatalVisitsActiveListingTest run failed before assertions
with facade bootstrap errors. No browser verification was performed. Existing
workspace trailing whitespace remains outside the edits from this task.
