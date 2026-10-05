<?php
namespace App\Http\Controllers;

use App\Models\Baby;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\PregnancyOutcomeRecordingService;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\ListNormalizer;
use App\Support\PregnancyOutcomeVocabulary;
use App\Support\WeightFormatter;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\ClinicalInteractionEvidence;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function __construct(
        private PregnancyOutcomeRecordingService $pregnancyOutcomeRecordingService,
        private PregnancyOutcomeMonitoringService $pregnancyOutcomeMonitoringService,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | Validate list controls
    |--------------------------------------------------------------------------
    |
    | These values come from the URL, so the backend should still validate
    | them instead of trusting arbitrary filter/search values.
    |
    */
    $validated = $request->validate([
        'filter' => ['nullable', Rule::in(['all', 'my'])],
        'search' => ['nullable', 'string', 'max:100'],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $filter = $validated['filter'] ?? 'all';
    $search = trim($validated['search'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Base patient query
    |--------------------------------------------------------------------------
    |
    | Patient Records contains active/ongoing pregnancy records only.
    | Load assignedStaff now to avoid repeatedly querying the user table
    | while rendering each patient row.
    |
    */
    $query = Patient::query()
        ->with('assignedStaff')
        ->where('status', 'ONGOING');

    /*
    |--------------------------------------------------------------------------
    | Ownership filter
    |--------------------------------------------------------------------------
    */
    if ($filter === 'my') {
        $query->where('assigned_staff_id', auth()->id());
    }

    /*
    |--------------------------------------------------------------------------
    | Server-side search
    |--------------------------------------------------------------------------
    |
    | Search the full database result set — not just the current page.
    |
    */
    if ($search !== '') {

    // Supports Patient ID searches:
    // PT-0017, PT0017, or 17
    $patientIdSearch = strtoupper($search);
    $patientIdSearch = preg_replace('/^PT-?/', '', $patientIdSearch);

    $query->where(function ($searchQuery) use ($search, $patientIdSearch) {

        $searchQuery
            ->where('first_name', 'like', "%{$search}%")
            ->orWhere('middle_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%");

        if (ctype_digit($patientIdSearch)) {
            $searchQuery->orWhere('id', (int) $patientIdSearch);
        }
    });
}

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    |
    | Statistics intentionally describe ALL ongoing patients rather than the
    | current search result so typing into the search box does not make the
    | headline numbers jump around.
    |
    */

    $ongoingQuery = Patient::query()
        ->where('status', 'ONGOING');

    $totalPatients = (clone $ongoingQuery)->count();

    $highRiskCount = (clone $ongoingQuery)
        ->whereHas('latestPrenatalAssessment', function ($visit) {
            $visit->where('risk_level', 'HIGH');
        })
        ->count();

    $myPatientsCount = (clone $ongoingQuery)
        ->where('assigned_staff_id', auth()->id())
        ->count();

    /*
    |--------------------------------------------------------------------------
    | Paginated patient list
    |--------------------------------------------------------------------------
    |
    | Stable alphabetical ordering makes Patient Records easier to browse.
    | Query parameters are preserved when moving between pages.
    |
    */
    $patients = $query
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->paginate(10)
    ->withQueryString();

    return view('patients.index', compact(
        'patients',
        'totalPatients',
        'highRiskCount',
        'myPatientsCount',
        'filter',
        'search'
    ));
}
    public function trashed()
    {
    $patients = Patient::onlyTrashed()->get();

    return view('patients.trashed', compact('patients'));
    }

    public function restore($id)
    {
    $patient = Patient::onlyTrashed()->findOrFail($id);

    $patient->restore(); // 🔥 triggers cascade restore

    return redirect()->route('patients.index')
        ->with('success', 'Patient restored successfully');
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('patients.create');
    }

    /**
     * Store a newly created resource in storage.
     */

    
   public function store(Request $request)
{
    $validated = $request->validate([
        'first_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:24',
        'middle_name' => 'nullable|regex:/^[a-zA-Z\s]+$/|max:24',
        'last_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:24',

        'birthdate' => 'required|date|before:today',
        'age' => 'required|integer|min:10|max:60',

        // New patients always provide the structured address fields. The
        // legacy `address` column is intentionally left blank for new
        // patients (never populated with a duplicated combined value).
        'address_line' => 'required|string|max:255',
        'barangay' => 'required|string|max:255',
        'city_municipality' => 'required|string|max:255',

        'contact_number' => ['required','regex:/^09\d{9}$/'],
        'email' => 'nullable|email|max:255',
        

        'civil_status' => 'required|in:Single,Married,Widowed',

        'philhealth_member' => 'required|in:0,1',
        'philhealth_number' => 'nullable|required_if:philhealth_member,1|max:255',

        'gravida' => 'required|integer|min:0',
        'para' => 'required|integer|min:0',

        'previous_cs' => 'required|in:0,1',
        'miscarriage' => 'required|integer|min:0',

        'lmp' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subWeeks(42)->toDateString(),
        'edd' => 'required|date|after:lmp',
    ],  [
        'first_name.max' => 'First name must not exceed 24 characters.',
        'middle_name.max' => 'Middle name must not exceed 24 characters.',
        'last_name.max' => 'Last name must not exceed 24 characters.',

        'first_name.regex' => 'First name contains invalid characters.',
        'middle_name.regex' => 'Middle name contains invalid characters.',
        'last_name.regex' => 'Last name contains invalid characters.',

        'lmp.before_or_equal' => 'Last menstrual period cannot be a future date.',
        'lmp.after_or_equal' => 'Last menstrual period must be within the last 42 weeks for an ongoing pregnancy.',
]);

    // LOGIC VALIDATION
    if ($request->para > $request->gravida) {
        return back()->withErrors(['para' => 'Para cannot exceed Gravida'])->withInput();
    }

    if ($request->miscarriage > $request->gravida) {
        return back()->withErrors(['miscarriage' => 'Miscarriage cannot exceed Gravida'])->withInput();
    }

    $data = $validated;
    $data['philhealth_member'] = $request->boolean('philhealth_member');
    $data['assigned_staff_id'] = auth()->id();

    if (!$data['philhealth_member']) {
        $data['philhealth_number'] = null;
    }

    $patient = Patient::create($data);

    $this->logAction(
        'CREATE',
        'PATIENT',
        'Added patient: ' . $patient->first_name . ' ' . $patient->last_name
    );

    return redirect()->route('patients.index')
        ->with('success', 'Patient has been successfully added.');
}

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $patient = Patient::with(['prenatalVisits','medicalHistory','ultrasounds','birthPlan','babies','referrals','pregnancyOutcome.followUpRecordedBy','pregnancyOutcome.confirmedBy'])->findOrFail($id);

        // Derived outcome-monitoring state for the profile card. Pure read:
        // no lifecycle or outcome values are written here.
        $monitoringState = $this->pregnancyOutcomeMonitoringService->deriveState($patient);
        $monitoringStateLabel = \App\Services\PregnancyOutcomeMonitoringService::stateLabel($monitoringState);
        $monitoringEligible = $this->pregnancyOutcomeMonitoringService->isFollowUpEligible($patient);
        $daysUntilOrPastEdd = $this->pregnancyOutcomeMonitoringService->daysUntilOrPastEdd($patient);

        // Application-controlled "Back" target: the monitoring URL the user
        // came from, validated as an internal pregnancy-outcomes URL. Null
        // when the profile was opened directly or the return URL is not safe.
        $monitoringReturnUrl = $this->resolveMonitoringReturnUrl($request);

        // Newest persisted prenatal visit, deterministically: created_at desc,
        // then id desc as a tie-breaker for records created in the same second.
        // Never rely on visit_date alone because multiple visits can share a date.
        $latestAssessment = $this->latestPrenatalVisit($patient);

        // Visit history table: newest-first, deterministic.
        $patient->setRelation(
            'prenatalVisits',
            $patient->prenatalVisits->sortByDesc(function ($visit) {
                return [$visit->created_at?->timestamp ?? 0, $visit->id];
            })->values()
        );

        // Check for required records before allowing prenatal visit creation
        $hasMedicalHistory = $patient->medicalHistory !== null;
        $hasUltrasound = $patient->ultrasounds()->exists();
        $hasBirthPlan = $patient->birthPlan !== null;
        $canAddPrenatalVisit = $hasMedicalHistory && $hasUltrasound && $hasBirthPlan;

        return view('patients.show', compact('patient', 'latestAssessment', 'hasMedicalHistory', 'hasUltrasound', 'hasBirthPlan', 'canAddPrenatalVisit', 'monitoringState', 'monitoringStateLabel', 'monitoringEligible', 'daysUntilOrPastEdd', 'monitoringReturnUrl'));
    }

    /**
     * Resolve the Pregnancy Outcome Monitoring "Back" target from the request.
     *
     * Only an internal, application-controlled pregnancy-outcomes URL is
     * accepted so the profile never opens an arbitrary/external redirect.
     * Returns null (safe fallback used in the view) when the return URL is
     * missing, not a URL, external, or not a monitoring page.
     */
    private function resolveMonitoringReturnUrl(Request $request): ?string
    {
        $candidate = $request->query('return');

        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $parts = parse_url($candidate);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $expectedHost = parse_url(route('pregnancy-outcomes.index'), PHP_URL_HOST);
        if ($host !== $expectedHost) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path !== '/' && ! str_starts_with($path, '/pregnancy-outcomes')) {
            return null;
        }

        return $candidate;
    }

    public function download(Request $request, string $id)
    {
        $request->validate([
            'format' => 'required|in:csv',
        ]);

        $patient = Patient::with(['prenatalVisits','medicalHistory','birthPlan','ultrasounds','babies','pregnancyOutcome'])->findOrFail($id);

        $missingFields = $this->getPatientDownloadMissingFields($patient);

        if (!empty($missingFields)) {
            return response()->json([
                'message' => 'Patient data is incomplete. Please complete required information before downloading.',
                'missing' => $missingFields,
            ], 422);
        }

        return $this->downloadPatientCsv($patient);
    }

    /**
     * Browser Print Patient Record (same-tab print page).
     *
     * Presentation-only counterpart of the DOMPDF download: same eager-loaded
     * relationships and the same deterministic latest prenatal visit, but it
     * renders a normal Blade page the browser can print (or save as PDF).
     * Deliberately does NOT apply the export missing-field gate (VALIDATION-B):
     * this record is already fully viewable on the profile page.
     */
    public function printPatientRecord($id)
    {
        $patient = Patient::with(['prenatalVisits','medicalHistory','birthPlan','ultrasounds','babies'])->findOrFail($id);

        return view('patients.print-patient-record', [
            'patient' => $patient,
            'latestVisit' => $this->latestPrenatalVisit($patient),
        ]);
    }

    private function getPatientDownloadMissingFields(Patient $patient): array
    {
        $missing = [];

        if (!$patient->first_name) {
            $missing[] = 'First name';
        }
        if (!$patient->last_name) {
            $missing[] = 'Last name';
        }
        if (!$patient->birthdate) {
            $missing[] = 'Birthdate';
        }
        if (!$patient->age) {
            $missing[] = 'Age';
        }
        if (!$patient->hasRecordedAddress()) {
            $missing[] = 'Address';
        }
        if (!$patient->contact_number) {
            $missing[] = 'Contact number';
        }
        if (!$patient->civil_status) {
            $missing[] = 'Civil status';
        }
        if ($patient->philhealth_member === null) {
            $missing[] = 'PhilHealth membership status';
        }
        if ($patient->philhealth_member && !$patient->philhealth_number) {
            $missing[] = 'PhilHealth number';
        }
        if ($patient->gravida === null) {
            $missing[] = 'Gravida';
        }
        if ($patient->para === null) {
            $missing[] = 'Para';
        }
        if (!$patient->lmp) {
            $missing[] = 'LMP';
        }
        if (!$patient->edd) {
            $missing[] = 'EDD';
        }
        if ($patient->status !== 'ONGOING' && !$patient->delivery_date) {
            $missing[] = 'Delivery date';
        }

        return $missing;
    }

    /**
 * Latest non-deleted prenatal visit by clinical visit date.
 *
 * visit_date determines which clinical encounter is latest.
 * id is used only as the tie-breaker when multiple records
 * share the same visit date.
 */
private function latestPrenatalVisit(Patient $patient): ?PrenatalVisit
{
    return $patient->prenatalVisits()
        ->orderByDesc('visit_date')
        ->orderByDesc('id')
        ->first();
}


    public function startNewPregnancy(Request $request, $id)
{
    $oldPatient = Patient::findOrFail($id);

    if ($oldPatient->status !== 'DELIVERED') {
        return back()->withErrors([
            'status' => 'New pregnancy can only be started from a delivered patient record.'
        ])->withInput();
    }

    $request->validate([
        'lmp' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subWeeks(42)->toDateString(),
        'edd' => 'required|date|after:lmp',
        'address' => 'required|string|max:255',
        'contact_number' => ['required', 'regex:/^09\d{9}$/'],
    ], [
        'lmp.before_or_equal' => 'Last menstrual period cannot be a future date.',
        'lmp.after_or_equal' => 'Last menstrual period must be within the last 42 weeks for an ongoing pregnancy.',
    ]);

    $hasActivePregnancy = Patient::where('first_name', $oldPatient->first_name)
        ->where('last_name', $oldPatient->last_name)
        ->where('birthdate', $oldPatient->birthdate)
        ->where('status', 'ONGOING')
        ->exists();

    if ($hasActivePregnancy) {
        return back()->withErrors([
            'status' => 'This patient already has an active ongoing pregnancy record.'
        ])->withInput();
    }

    $newPatient = Patient::create([
        'first_name' => $oldPatient->first_name,
        'middle_name' => $oldPatient->middle_name,
        'last_name' => $oldPatient->last_name,
        'birthdate' => $oldPatient->birthdate,
        'age' => Carbon::parse($oldPatient->birthdate)->age,

        'address' => $request->address,
        'contact_number' => $request->contact_number,

        'email' => $oldPatient->email,
        'civil_status' => $oldPatient->civil_status,
        'philhealth_member' => $oldPatient->philhealth_member,
        'philhealth_number' => $oldPatient->philhealth_number,

        'gravida' => $oldPatient->gravida + 1,
        'para' => $oldPatient->para,
        'previous_cs' => $oldPatient->previous_cs,
        'miscarriage' => $oldPatient->miscarriage,

        'lmp' => $request->lmp,
        'edd' => $request->edd,
        'status' => 'ONGOING',
        'delivery_date' => null,
    ]);

    $this->logAction(
        'CREATE',
        'PATIENT',
        'Started new pregnancy record for: ' . $oldPatient->first_name . ' ' . $oldPatient->last_name
    );

    return redirect()->route('patients.show', $newPatient->id)
        ->with('success', 'New pregnancy record created successfully.');
}

    /**
     * Structured Patient Record CSV (Section, Field, Value — Format Version 2).
     *
     * One fact per record so a spreadsheet never expands a multiline cell into
     * several physical rows. Every value is stored or already-derived
     * application data: this method never recalculates risk, never invents a
     * placeholder record, and never emits raw JSON, storage paths, audit
     * timestamps, foreign keys, or the Safety Disclaimer narrative.
     */
    private function downloadPatientCsv(Patient $patient)
    {
        $handle = fopen('php://memory', 'r+');

        foreach ($this->patientRecordCsvRows($patient) as $row) {
            fputcsv(
                $handle,
                array_map(fn ($value) => $this->csvCellValue($value), $row),
                ',',
                '"',
                ''
            );
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        // UTF-8 BOM: Excel on Windows only renders °C / • / – with a BOM.
        $content = "\xEF\xBB\xBF" . $content;

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->patientRecordFilename($patient, 'csv') . '"',
        ]);
    }

    /**
     * Every exported row as [Section, Field, Value], in a fixed order.
     *
     * A section with no stored record is omitted instead of being invented
     * (no Birth Plan section without a birth plan, no Risk Assessment section
     * without a visit, no fake "No risk data available" placeholder).
     *
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function patientRecordCsvRows(Patient $patient): array
    {
        return array_merge(
            [['Section', 'Field', 'Value']],
            $this->csvPatientInformationRows($patient),
            $this->csvCurrentPregnancyRows($patient),
            $this->csvMedicalHistoryRows($patient),
            $this->csvPrenatalVisitRows($patient),
            $this->csvRiskAssessmentRows($patient),
            $this->csvBirthPlanRows($patient),
            $this->csvUltrasoundRows($patient),
            $this->csvBabyRows($patient),
            $this->csvPregnancyOutcomeRows($patient),
            $this->csvExportMetadataRows(),
        );
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvPatientInformationRows(Patient $patient): array
    {
        $section = 'Patient Information';

        $fullName = trim(
            $patient->first_name . ' '
            . ($patient->middle_name ? $patient->middle_name . ' ' : '')
            . $patient->last_name
        );

        // Structured components take precedence over the legacy single-column
        // address, exactly like Patient::getFormattedAddressAttribute().
        $hasStructuredAddress = trim((string) $patient->address_line) !== ''
            || trim((string) $patient->barangay) !== ''
            || trim((string) $patient->city_municipality) !== '';

        $rows = [
            [$section, 'Patient ID', $patient->id],
            [$section, 'Full Name', $fullName],
            [$section, 'Birthdate', $this->csvDate($patient->birthdate)],
            [$section, 'Age', $patient->age],
            [$section, 'Civil Status', $patient->civil_status],
            [$section, 'Address Line', $hasStructuredAddress ? $patient->address_line : $patient->address],
            [$section, 'Barangay', $hasStructuredAddress ? $patient->barangay : null],
            [$section, 'City / Municipality', $hasStructuredAddress ? $patient->city_municipality : null],
            [$section, 'Contact Number', $patient->contact_number],
        ];

        if ($patient->email) {
            $rows[] = [$section, 'Email Address', $patient->email];
        }

        $rows[] = [$section, 'PhilHealth Member', $patient->philhealth_member ? 'Yes' : 'No'];

        if ($patient->philhealth_number) {
            $rows[] = [$section, 'PhilHealth Number', $patient->philhealth_number];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvCurrentPregnancyRows(Patient $patient): array
    {
        $section = 'Current Pregnancy';

        $rows = [
            [$section, 'Gravida', $patient->gravida],
            [$section, 'Para', $patient->para],
            [$section, 'LMP', $this->csvDate($patient->lmp)],
            [$section, 'EDD', $this->csvDate($patient->edd)],
            [$section, 'Pregnancy Status', $this->csvPregnancyStatus($patient->status)],
        ];

        if ($patient->delivery_date !== null) {
            $rows[] = [$section, 'Delivery Date', $this->csvDate($patient->delivery_date)];
        }

        if ($patient->previous_cs !== null) {
            $rows[] = [$section, 'Previous Cesarean Section', $patient->previous_cs ? 'Yes' : 'No'];
        }

        if ($patient->miscarriage !== null) {
            $rows[] = [$section, 'Previous Miscarriage Count', $patient->miscarriage];
        }

        return $rows;
    }

    /**
     * MedicalHistory is not guaranteed: the export gate does not require one,
     * so a missing record must never be dereferenced and must never be
     * fabricated into condition rows. The section states Recorded: No only.
     *
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvMedicalHistoryRows(Patient $patient): array
    {
        $section = 'Medical History';
        $history = $patient->medicalHistory;

        if ($history === null) {
            return [[$section, 'Recorded', 'No']];
        }

        $rows = [[$section, 'Recorded', 'Yes']];

        $conditions = [
            'Epilepsy' => 'epilepsy',
            'Severe Headache' => 'severe_headache',
            'Visual Disturbance' => 'visual_disturbance',
            'Chest Pain' => 'chest_pain',
            'Shortness of Breath' => 'shortness_breath',
            'Breast Mass' => 'breast_mass',
            'Liver Disease' => 'liver_disease',
            'Smoking' => 'smoking',
            'Allergies' => 'allergies',
            'Drug Intake' => 'drug_intake',
            'STD History' => 'std_history',
            'Diabetes' => 'diabetes',
            'Hypertension' => 'hypertension',
            'Asthma' => 'asthma',
            'Thyroid Disease' => 'thyroid_disease',
            'Heart Disease' => 'heart_disease',
            'Anemia' => 'anemia',
            'Mental Health Condition' => 'mental_health_condition',
        ];

        foreach ($conditions as $label => $column) {
            $rows[] = [$section, $label, $history->{$column} ? 'Yes' : 'No'];
        }

        if ($history->other_specify) {
            $rows[] = [$section, 'Other', $history->other_specify];
        }

        return $rows;
    }

    /**
     * All prenatal visits, newest first (visit_date, then id as tie-breaker).
     * The newest encounter is section "Prenatal Visit 1 (Latest)"; older
     * assessments stay in the export rather than being dropped.
     *
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvPrenatalVisitRows(Patient $patient): array
    {
        $sectionLabel = 'Prenatal Visit';

        $visits = $patient->prenatalVisits()
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        $rows = [];

        foreach ($visits->values() as $index => $visit) {
            $number = $index + 1;
            $section = $number === 1
                ? $sectionLabel . ' 1 (Latest)'
                : $sectionLabel . ' ' . $number;

            $rows[] = [$section, 'Visit Date', $this->csvDate($visit->visit_date)];
            $rows[] = [$section, 'Is Latest Visit', $number === 1 ? 'Yes' : 'No'];
            $rows[] = [$section, 'Blood Pressure', $this->csvBloodPressure($visit->bp_sys, $visit->bp_dia)];
            $rows[] = [$section, 'Repeat Blood Pressure', $this->csvBloodPressure($visit->repeat_bp_sys, $visit->repeat_bp_dia)];
            $rows[] = [$section, 'Repeat BP Recorded At', $this->csvDateTime($visit->repeat_bp_recorded_at)];
            $rows[] = [$section, 'BP Verification Status', $visit->bp_verification_status ? str_replace('_', ' ', $visit->bp_verification_status) : null];
            $rows[] = [$section, 'Weight', $visit->weight !== null ? WeightFormatter::formatKg($visit->weight) . ' kg' : null];
            $rows[] = [$section, 'Temperature', $visit->temperature !== null ? $this->csvNumber($visit->temperature) . ' °C' : null];
            $rows[] = [$section, 'Gestational Age', $visit->gestational_age !== null ? $this->csvNumber($visit->gestational_age) . ' wks' : null];
            $rows[] = [$section, 'Risk Factors', implode(' | ', $this->identifiedRiskFactors($visit))];
            $rows[] = [$section, 'Clinical Assessment', $visit->assessment];
            $rows[] = [$section, 'Recommended Action', $visit->recommendation];
            $rows[] = [$section, 'Next Visit Date', $this->csvDate($visit->next_visit_date)];
        }

        return $rows;
    }

    /**
     * Risk Assessment is the stored summary of the latest visit only. Without
     * a visit there is no stored assessment to export, so the section is
     * omitted rather than filled with placeholders or a fresh recalculation.
     *
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvRiskAssessmentRows(Patient $patient): array
    {
        $latestVisit = $this->latestPrenatalVisit($patient);

        if ($latestVisit === null) {
            return [];
        }

        $section = 'Risk Assessment';

        $rows = [
            [$section, 'Risk Level', $latestVisit->risk_level],
            [$section, 'Decision Source', $this->csvDecisionSourceLabel($latestVisit->decision_source)],
        ];

        if ($latestVisit->urgency) {
            $rows[] = [$section, 'Urgency', $this->csvUrgencyLabel($latestVisit->urgency)];
        }

        $identifiedFactors = $this->identifiedRiskFactors($latestVisit, true);

        foreach ($identifiedFactors as $factor) {
            $rows[] = [$section, 'Identified Risk Factor', $factor];
        }

        $bpAssessment = is_array($latestVisit->bp_assessment) ? $latestVisit->bp_assessment : [];

        if (!empty($bpAssessment['label'])) {
            $rows[] = [$section, 'BP Classification', $bpAssessment['label']];
        }
        $bpInterpretation = $bpAssessment['interpretation'] ?? $bpAssessment['clinical_interpretation'] ?? null;
        if ($bpInterpretation) {
            $rows[] = [$section, 'BP Interpretation', $bpInterpretation];
        }
        $bpAction = $bpAssessment['action'] ?? $bpAssessment['suggested_action'] ?? null;
        if ($bpAction) {
            $rows[] = [$section, 'BP Action', $bpAction];
        }

        $rows[] = [$section, 'Clinical Assessment', $latestVisit->assessment];
        $rows[] = [$section, 'Recommended Action', $latestVisit->recommendation];

        if ($latestVisit->next_visit_date !== null) {
            $rows[] = [$section, 'Recommended Follow-up', $this->csvDate($latestVisit->next_visit_date)];
        }

        $rows[] = [$section, 'Next Visit Status', $this->csvNextVisitStatus($patient, $latestVisit)];

        if ($latestVisit->decision_source === 'RULE_BASED') {
            foreach (ListNormalizer::normalize($latestVisit->rule_reasons) as $rule) {
                $rows[] = [$section, 'Triggered Clinical Rule', $rule];
            }
        }

        if ($latestVisit->decision_source === 'COMPLETENESS') {
            foreach (ListNormalizer::normalize($latestVisit->missing_records) as $record) {
                $rows[] = [$section, 'Missing Required Record', $record];
            }
        }

        if ($latestVisit->decision_source === 'MACHINE_LEARNING') {
            if ($latestVisit->ml_prediction) {
                $rows[] = [$section, 'ML Prediction', $latestVisit->ml_prediction];
            }
            $rows[] = [$section, 'ML Valid', $latestVisit->ml_valid ? 'Yes' : 'No'];
        }

        foreach (ClinicalFactorEvidence::normalizeList($latestVisit->factor_evidence) as $factor) {
            $rows[] = [$section, 'Structured Clinical Factor', $this->csvFactorEvidenceValue($factor)];
        }

        $metadata = is_array($latestVisit->assessment_metadata) ? $latestVisit->assessment_metadata : [];
        $interactions = ClinicalInteractionEvidence::normalizeList(
            $metadata['interaction_evidence'] ?? $latestVisit->interaction_evidence ?? []
        );

        foreach ($interactions as $interaction) {
            $rows[] = [$section, 'Clinical Interaction', $this->csvInteractionEvidenceValue($interaction)];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvBirthPlanRows(Patient $patient): array
    {
        $birthPlan = $patient->birthPlan;

        if ($birthPlan === null) {
            return [];
        }

        $section = 'Birth Plan';

        return [
            [$section, 'Planned Prenatal Visits', $birthPlan->planned_visits],
            [$section, 'Deliver in Clinic', $birthPlan->deliver_in_clinic ? 'Yes' : 'No'],
            [$section, 'Delivery Location', $birthPlan->delivery_location],
            [$section, 'Transportation', $birthPlan->transportation],
            [$section, 'Transport Cost Needed', $birthPlan->transport_cost ? 'Yes' : 'No'],
            [$section, 'Payment Method', $birthPlan->payment_method],
            [$section, 'Saving Started', $birthPlan->saving_started ? 'Yes' : 'No'],
            [$section, 'Birth Companion', $birthPlan->birth_companion],
            [$section, 'Caregiver at Home', $birthPlan->caregiver_home],
            [$section, 'Plan More Children', $birthPlan->plan_more_children ? 'Yes' : 'No'],
            [$section, 'Number of More Children', $birthPlan->number_more_children],
            [$section, 'Knows Family Planning Method', $birthPlan->knows_fp_method ? 'Yes' : 'No'],
            [$section, 'Used Family Planning Before', $birthPlan->used_fp_before ? 'Yes' : 'No'],
            [$section, 'Family Planning Method', $birthPlan->family_planning_method],
            [$section, 'FP Source', $birthPlan->fp_source],
            [$section, 'Notes', $birthPlan->notes],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvUltrasoundRows(Patient $patient): array
    {
        $scans = $patient->ultrasounds()
            ->orderByDesc('scan_date')
            ->orderByDesc('id')
            ->get();

        if ($scans->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($scans->values() as $index => $scan) {
            $section = 'Ultrasound ' . ($index + 1);

            $rows[] = [$section, 'Scan Date', $this->csvDate($scan->scan_date)];
            $rows[] = [$section, 'Gestational Age at Scan', $scan->gestational_age_scan !== null ? $this->csvNumber($scan->gestational_age_scan) . ' wks' : null];
            $rows[] = [$section, 'Presentation', $scan->presentation];
            $rows[] = [$section, 'Amniotic Fluid', $scan->amniotic_fluid];
            $rows[] = [$section, 'Placenta Position', $scan->placenta_position];
            $rows[] = [$section, 'Fetal Heartbeat', $scan->fetal_heartbeat];
            $rows[] = [$section, 'Fetal Movement', $scan->fetal_movement];
            $rows[] = [$section, 'Estimated Fetal Weight', $scan->estimated_fetal_weight];
            $rows[] = [$section, 'Remarks', $scan->remarks];
            $rows[] = [$section, 'Report On File', ($scan->report_file || $scan->report_image) ? 'Yes' : 'No'];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvBabyRows(Patient $patient): array
    {
        $babies = $patient->babies;

        if ($babies->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($babies->values() as $index => $baby) {
            $section = 'Baby ' . ($index + 1);

            $rows[] = [$section, 'Full Name', $baby->full_name];
            $rows[] = [$section, 'Sex', $baby->sex];
            $rows[] = [$section, 'Date of Birth', $this->csvDate($baby->date_of_birth)];
            $rows[] = [$section, 'Time of Birth', $this->csvTime($baby->time_of_birth)];
            $rows[] = [$section, 'Birth Weight', $baby->birth_weight !== null ? WeightFormatter::formatKg($baby->birth_weight) . ' kg' : null];
            $rows[] = [$section, 'Birth Length', $baby->birth_length !== null ? $this->csvNumber($baby->birth_length) . ' cm' : null];
        }

        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvPregnancyOutcomeRows(Patient $patient): array
    {
        $outcome = $patient->pregnancyOutcome;

        if ($outcome === null) {
            return [];
        }

        $section = 'Pregnancy Outcome';

        return [
            [$section, 'Outcome Type', $outcome->outcome_type],
            [$section, 'Delivery Location', $outcome->delivery_location ? PregnancyOutcomeVocabulary::deliveryLocationLabel($outcome->delivery_location) : null],
            [$section, 'Confirmation Source', $outcome->confirmation_source ? PregnancyOutcomeVocabulary::confirmationSourceLabel($outcome->confirmation_source) : null],
            [$section, 'Confirmed At', $this->csvDateTime($outcome->confirmed_at)],
            [$section, 'Follow-up Status', $outcome->follow_up_status ? PregnancyOutcomeVocabulary::followUpStatusLabel($outcome->follow_up_status) : null],
            [$section, 'Notes', $outcome->notes],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: mixed}>
     */
    private function csvExportMetadataRows(): array
    {
        $section = 'Export';

        return [
            [$section, 'Exported At', now()->format('Y-m-d H:i')],
            [$section, 'Format Version', 2],
        ];
    }

    /**
     * Plain-language identified risk factors, mirroring the profile and print
     * views: rule reasons first, then risk reasons, de-duplicated. When
     * $withBpLabel is true, a BP-URG classification is prepended the same way
     * the print view does.
     *
     * @return array<int, string>
     */
    private function identifiedRiskFactors(PrenatalVisit $visit, bool $withBpLabel = false): array
    {
        $factors = array_values(array_unique(array_merge(
            ListNormalizer::normalize($visit->rule_reasons),
            ListNormalizer::normalize($visit->risk_reasons),
        )));

        $bpAssessment = is_array($visit->bp_assessment) ? $visit->bp_assessment : [];

        if ($withBpLabel
            && ($bpAssessment['reason_code'] ?? null) === 'BP-URG'
            && !empty($bpAssessment['label'])
            && !in_array($bpAssessment['label'], $factors, true)) {
            array_unshift($factors, $bpAssessment['label']);
        }

        return $factors;
    }

    /**
     * Single-line rendering of one structured clinical-factor evidence row,
     * reusing the print view's labels and observed-value formatter.
     *
     * @param array<string, mixed> $factor
     */
    private function csvFactorEvidenceValue(array $factor): string
    {
        $sourceLabels = [
            'MATERNAL_DEMOGRAPHICS' => 'Maternal demographics',
            'VITAL_SIGNS' => 'Vital signs',
            'CURRENT_CONDITION' => 'Current condition',
            'OBSTETRIC_HISTORY' => 'Obstetric history',
            'ULTRASOUND' => 'Ultrasound finding',
        ];

        $category = (string) ($factor['category'] ?? '');
        $source = $sourceLabels[$category] ?? ($category !== '' ? $category : null);

        $parts = [trim((string) ($factor['label'] ?? '')) . ' (' . (string) ($factor['code'] ?? '') . ')'];

        if ($source !== null && $source !== '') {
            $parts[] = 'Source: ' . $source;
        }

        $observed = ClinicalFactorEvidence::displayObserved($factor['observed_value'] ?? null);
        if ($observed !== '' && $observed !== '—') {
            $parts[] = 'Observed: ' . $observed;
        }

        if (!empty($factor['threshold_or_rule'])) {
            $parts[] = 'Rule / Threshold: ' . $factor['threshold_or_rule'];
        }

        if (!empty($factor['explanation'])) {
            $parts[] = (string) $factor['explanation'];
        }

        if (!empty($factor['suggested_action'])) {
            $parts[] = 'Action: ' . $factor['suggested_action'];
        }

        return implode(' · ', $parts);
    }

    /**
     * Single-line rendering of one clinical interaction evidence row, reusing
     * the print view's labels.
     *
     * @param array<string, mixed> $interaction
     */
    private function csvInteractionEvidenceValue(array $interaction): string
    {
        $contextLabels = [
            'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
            'ultrasound_inputs.presentation' => 'Fetal presentation',
        ];

        $parts = [trim((string) ($interaction['label'] ?? '')) . ' (' . (string) ($interaction['code'] ?? '') . ')'];

        $factorCodes = array_values(array_filter((array) ($interaction['required_factor_codes'] ?? []), 'is_string'));
        if ($factorCodes !== []) {
            $parts[] = 'Factors: ' . implode(', ', $factorCodes);
        }

        $observedLines = [];
        foreach ((array) ($interaction['observed_context'] ?? []) as $path => $value) {
            $label = $contextLabels[$path] ?? null;
            if ($label !== null && $value !== null && trim((string) $value) !== '') {
                $observedLines[] = $label . ': ' . $value;
            }
        }
        if ($observedLines !== []) {
            $parts[] = implode(' · ', $observedLines);
        }

        if (!empty($interaction['explanation'])) {
            $parts[] = (string) $interaction['explanation'];
        }

        if (!empty($interaction['suggested_action'])) {
            $parts[] = 'Action: ' . $interaction['suggested_action'];
        }

        return implode(' · ', $parts);
    }

    /**
     * Follow-up status, identical to the print view's logic. Never reports
     * Overdue/On schedule for a pregnancy that already closed.
     */
    private function csvNextVisitStatus(Patient $patient, PrenatalVisit $latestVisit): string
    {
        if ($patient->status === 'DELIVERED') {
            return 'Delivered';
        }

        if ($patient->status === 'REFERRED') {
            return 'Referred';
        }

        if ($latestVisit->next_visit_date === null) {
            return 'Not scheduled';
        }

        return Carbon::parse($latestVisit->next_visit_date)->isPast() ? 'Overdue' : 'On schedule';
    }

    private function csvDecisionSourceLabel(?string $decisionSource): string
    {
        return match ($decisionSource) {
            'COMPLETENESS' => 'Completeness Check',
            'RULE_BASED' => 'Clinical Rules',
            'MACHINE_LEARNING' => 'Machine Learning',
            'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
            null => 'Legacy Assessment',
            default => $decisionSource,
        };
    }

    private function csvUrgencyLabel(string $urgency): string
    {
        $labels = [
            'URGENT_CLINICAL_REVIEW' => 'Urgent Clinical Review',
            'PROMPT' => 'Prompt Clinical Review',
        ];

        return $labels[$urgency] ?? $urgency;
    }

    /**
     * Only the three approved pregnancy states; REFERRED must never collapse
     * into Ongoing.
     */
    private function csvPregnancyStatus(?string $status): ?string
    {
        return match ($status) {
            'ONGOING' => 'Ongoing',
            'DELIVERED' => 'Delivered',
            'REFERRED' => 'Referred',
            default => $status,
        };
    }

    private function csvBloodPressure(mixed $systolic, mixed $diastolic): ?string
    {
        if ($systolic === null || $diastolic === null || $systolic === '' || $diastolic === '') {
            return null;
        }

        return $systolic . '/' . $diastolic;
    }

    private function csvDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    private function csvDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function csvTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('g:i A');
        }

        return Carbon::parse($value)->format('g:i A');
    }

    /**
     * Numeric rendering without float artefacts: database DECIMAL strings keep
     * their stored scale (49.0 stays 49.0), while PHP floats are trimmed to a
     * clean decimal (38.2 never becomes 38.200000000000003).
     */
    private function csvNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $formatted = rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.');

        return ($formatted === '' || $formatted === '-0') ? '0' : $formatted;
    }

    /**
     * Central cell normalizer applied to every value in the export.
     *
     * - null / empty stays an empty cell (no N/A proliferation)
     * - booleans become Yes/No
     * - floats are rendered without binary artefacts
     * - datetimes are never printed as 00:00:00
     * - embedded CR/LF collapse into " | " so one fact is always one record
     * - a leading formula character is neutralised with a leading apostrophe,
     *   except for numeric text, which is safe as-is
     */
    private function csvCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $this->csvNumber($value);
        }

        if ($value instanceof \DateTimeInterface) {
            $hasTime = $value->format('H') !== '00'
                || $value->format('i') !== '00'
                || $value->format('s') !== '00';

            return $hasTime ? $value->format('Y-m-d H:i') : $value->format('Y-m-d');
        }

        $text = (string) $value;

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $text = implode(' | ', array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== ''
        )));

        if ($text !== '' && !is_numeric($text) && preg_match('/^[=+\-@\t]/', $text) === 1) {
            $text = "'" . $text;
        }

        return $text;
    }

    /**
     * Build a safe, patient-name-based download filename.
     *
     * Format: "<Sanitized-Full-Name>-<Patient-ID>-Patient-Record.<ext>"
     * e.g. Jesa-Pro-79-Patient-Record.pdf. The name is trimmed, sanitized to
     * ASCII letters/digits (separators become single dashes), and collapses to
     * a safe "Patient-<ID>" fallback when the name sanitizes to nothing.
     */
    private function patientRecordFilename(Patient $patient, string $extension): string
    {
        $fullName = trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name);

        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $fullName);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            return 'Patient-' . $patient->id . '-Patient-Record.' . $extension;
        }

        return $slug . '-' . $patient->id . '-Patient-Record.' . $extension;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $patient = \App\Models\Patient::findOrFail($id);

    return view('patients.edit', compact('patient'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
{
    $patient = Patient::findOrFail($id);

     // ======================
    // VALIDATION
    // ======================
    $validated = $request->validate([
    'first_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:24',
    'middle_name' => 'nullable|regex:/^[a-zA-Z\s]+$/|max:24',
    'last_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:24',

    'birthdate' => 'required|date|before:today',
    'age' => 'required|integer|min:10|max:60',

    // Structured address fields are optional on edit so a legacy patient
    // (who only has the old `address` value) can still be updated without
    // being forced to convert their address. Once staff start filling in
    // any one of the three, all three become required together so the
    // structured address is never left half-entered.
    'address_line' => 'nullable|string|max:255|required_with:barangay,city_municipality',
    'barangay' => 'nullable|string|max:255|required_with:address_line,city_municipality',
    'city_municipality' => 'nullable|string|max:255|required_with:address_line,barangay',

    'contact_number' => ['required','regex:/^09\d{9}$/'],
    'email' => 'nullable|email|max:255',

    'civil_status' => 'required|in:Single,Married,Widowed',

    'philhealth_member' => 'required|in:0,1',
    'philhealth_number' => 'nullable|required_if:philhealth_member,1|max:255',

    'gravida' => 'required|integer|min:0',
    'para' => 'required|integer|min:0',

    'previous_cs' => 'required|in:0,1',
    'miscarriage' => 'required|integer|min:0',

    'lmp' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subWeeks(42)->toDateString(),
    'edd' => 'nullable|date|after:lmp',
], [
    'lmp.before_or_equal' => 'Last menstrual period cannot be a future date.',
    'lmp.after_or_equal' => 'Last menstrual period must be within the last 42 weeks for an ongoing pregnancy.',
]);
if ($request->para > $request->gravida) {
    return back()->withErrors([
        'para' => 'Para cannot exceed Gravida.'
    ])->withInput();
}

if ($request->miscarriage > $request->gravida) {
    return back()->withErrors([
        'miscarriage' => 'Miscarriage cannot exceed Gravida.'
    ])->withInput();
}


    $data = $validated;
    $data['philhealth_member'] = $request->boolean('philhealth_member');

    if (!$data['philhealth_member']) {
        $data['philhealth_number'] = null;
    }

    $patient->update($data);

   



    // ✅ AUDIT LOG
$this->logAction(
    'UPDATE',
    'PATIENT',
    'Updated patient: ' . $patient->first_name . ' ' . $patient->last_name
);

    return redirect()->route('patients.index')
        ->with('success', 'Patient updated successfully!');
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
{
    $patient = \App\Models\Patient::findOrFail($id);

    $name = $patient->first_name . ' ' . $patient->last_name;

    $patient->delete();

    // ✅ AUDIT LOG
    $this->logAction(
        'DELETE',
        'PATIENT',
        'Deleted patient: ' . $name
    );

    return redirect()->route('patients.index');
}

public function markDelivered(Request $request, $id)
{
    $patient = Patient::findOrFail($id);

    // Validate delivery data
    $validated = $request->validate([
        'delivery_date' => 'required|date|before_or_equal:today',
        'delivery_location' => ['required', Rule::in(PregnancyOutcomeVocabulary::DELIVERY_LOCATIONS)],
        'confirmation_source' => ['required', Rule::in(PregnancyOutcomeVocabulary::CONFIRMATION_SOURCES)],
        'outcome_notes' => 'nullable|string|max:2000',
        'babies' => 'array|min:1',
        'babies.*.date_of_birth' => 'required|date|before_or_equal:today|same:delivery_date',
        'babies.*.time_of_birth' => 'required|date_format:H:i',
        'babies.*.first_name' => 'nullable|string|max:24',
        'babies.*.middle_name' => 'nullable|string|max:24',
        'babies.*.last_name' => 'nullable|string|max:24',
        'babies.*.sex' => 'nullable|in:Male,Female',
        'babies.*.birth_weight' => ['nullable', 'numeric', 'min:0', 'max:10', 'regex:/^\d+(\.\d)?$/'],
        'babies.*.birth_length' => 'nullable|numeric|min:0|max:100',
    ], [
        'babies.*.birth_weight.regex' => 'Birth weight must have at most 1 decimal place.',
    ]);

    // Check if at least one baby has required fields
    $babiesData = $request->babies ?? [];
    if (empty($babiesData)) {
        return back()->withErrors(['babies' => 'At least one baby record is required.'])->withInput();
    }

    // Validate that each baby has date and time of birth
    foreach ($babiesData as $index => $babyData) {
        if (empty($babyData['date_of_birth']) || empty($babyData['time_of_birth'])) {
            return back()->withErrors([
                "babies.{$index}.date_of_birth" => "Baby " . ($index + 1) . ": Date and time of birth are required."
            ])->withInput();
        }
    }

    // The service owns the entire confirmed-delivery write transaction
    // (patient lifecycle, outcome record + provenance, babies, para).
    try {
        $this->pregnancyOutcomeRecordingService->recordConfirmedDelivery(
            $patient,
            $request->user(),
            $validated['delivery_date'],
            $validated['delivery_location'],
            $validated['confirmation_source'],
            $babiesData,
            $validated['outcome_notes'] ?? null
        );
    } catch (DomainException $e) {
        return back()->withErrors(['status' => $e->getMessage()])->withInput();
    }

    // Audit only AFTER the transaction committed.
    $babyLabel = count($babiesData) === 1 ? 'baby' : 'babies';
    $this->logAction(
        'UPDATE',
        'PATIENT',
        'Recorded confirmed delivery outcome for ' . $patient->first_name . ' ' . $patient->last_name
            . ' with ' . count($babiesData) . ' ' . $babyLabel . '.'
    );

    return redirect()->route('patients.delivered')
        ->with('success', 'Patient marked as delivered with baby information recorded.');
}
public function delivered()
{
    $search = trim((string) request('search'));

    $deliveredPatients = Patient::with(['babies', 'pregnancyOutcome.confirmedBy'])
        ->where('status', 'DELIVERED')
        ->orderByDesc('delivery_date')
        ->orderByDesc('created_at')
        ->get();

    if ($search !== '') {
        $deliveredPatients = $deliveredPatients->filter(function ($patient) use ($search) {
            $name = trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name);

            return str_contains(strtolower($name), strtolower($search))
                || str_contains((string) $patient->contact_number, $search);
        })->values();
    }

    $groupedPatients = $deliveredPatients
        ->groupBy(fn ($patient) => $this->patientHistoryKey($patient))
        ->map(function ($pregnancies) {
            $latest = $pregnancies->sortByDesc(fn ($patient) => $patient->delivery_date ?: $patient->created_at)->first();
            $outcome = $latest->pregnancyOutcome;

            return (object) [
                'patient' => $latest,
                'completed_pregnancies' => $pregnancies->count(),
                'total_babies' => $pregnancies->sum(fn ($patient) => $patient->babies->count()),
                'last_delivery_date' => $pregnancies->max('delivery_date'),
                'confirmed' => $outcome && $outcome->hasConfirmedOutcome(),
            ];
        })
        ->sortByDesc('last_delivery_date')
        ->values();

    $page = LengthAwarePaginator::resolveCurrentPage();
    $perPage = 10;
    $patients = new LengthAwarePaginator(
        $groupedPatients->forPage($page, $perPage)->values(),
        $groupedPatients->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return view('patients.delivered', compact('patients'));
}

public function pregnancyHistory($id)
{
    $patient = Patient::with('babies')->where('status', 'DELIVERED')->findOrFail($id);
    $pregnancies = $this->completedPregnanciesFor($patient);
    $latestPatient = $pregnancies->first();

    return view('patients.pregnancy-history', [
        'patient' => $latestPatient,
        'pregnancies' => $pregnancies,
        'totalBabies' => $pregnancies->sum(fn ($pregnancy) => $pregnancy->babies->count()),
    ]);
}

public function babyInformation($id)
{
    $pregnancy = Patient::with(['babies', 'prenatalVisits', 'pregnancyOutcome.confirmedBy'])
        ->where('status', 'DELIVERED')
        ->findOrFail($id);

    return view('patients.baby-information', [
        'pregnancy' => $pregnancy,
        'latestVisit' => $pregnancy->prenatalVisits->sortByDesc('visit_date')->first(),
    ]);
}

    public function printBabies(Request $request, $id)
    {
        $patient = Patient::with('babies')->where('status', 'DELIVERED')->findOrFail($id);

        $pregnancyId = $request->integer('pregnancy_id');

        if ($pregnancyId) {
            $pregnancies = $this->completedPregnanciesFor($patient)
                ->where('id', $pregnancyId)
                ->values();
        } elseif ($request->boolean('all')) {
            $pregnancies = $this->completedPregnanciesFor($patient);
        } else {
            $pregnancies = collect([$patient->load(['babies', 'prenatalVisits', 'pregnancyOutcome.confirmedBy'])]);
        }

        $babyId = $request->integer('baby_id');

        if ($babyId) {
            $pregnancies = $pregnancies->map(function ($pregnancy) use ($babyId) {
                $pregnancy->setRelation('babies', $pregnancy->babies->where('id', $babyId)->values());

                return $pregnancy;
            })->filter(fn ($pregnancy) => $pregnancy->babies->isNotEmpty())->values();
        }

        return view('patients.print-babies', compact('patient', 'pregnancies'));
    }

private function completedPregnanciesFor(Patient $patient)
{
    return Patient::with(['babies', 'prenatalVisits', 'pregnancyOutcome.confirmedBy'])
        ->where('status', 'DELIVERED')
        ->where('first_name', $patient->first_name)
        ->where('last_name', $patient->last_name)
        ->when($patient->middle_name, fn ($query) => $query->where('middle_name', $patient->middle_name), fn ($query) => $query->whereNull('middle_name'))
        ->when($patient->birthdate, fn ($query) => $query->where('birthdate', $patient->birthdate), fn ($query) => $query->whereNull('birthdate'))
        ->orderByDesc('delivery_date')
        ->orderByDesc('created_at')
        ->get();
}

private function patientHistoryKey(Patient $patient): string
{
    return strtolower(trim($patient->first_name . '|' . $patient->middle_name . '|' . $patient->last_name . '|' . $patient->birthdate));
}

public function updateBaby(Request $request, $id)
{
    $request->validate([
        'first_name' => 'nullable|string|max:24',
        'middle_name' => 'nullable|string|max:24',
        'last_name' => 'nullable|string|max:24',
        'sex' => 'nullable|in:Male,Female',
        'date_of_birth' => 'required|date',
        'time_of_birth' => 'required|date_format:H:i',
        'birth_weight' => ['nullable', 'numeric', 'min:0', 'max:10', 'regex:/^\d+(\.\d)?$/'],
        'birth_length' => 'nullable|numeric|min:0|max:100',
    ], [
        'birth_weight.regex' => 'Birth weight must have at most 1 decimal place.',
    ]);

    $baby = Baby::with('patient')->findOrFail($id);

    // Server-side lifecycle safety: baby records on a closed (DELIVERED) or
    // legacy REFERRED pregnancy are immutable. The UI already hides the edit
    // controls, but the backend must be authoritative.
    if ($baby->patient && in_array($baby->patient->status, ['DELIVERED', 'REFERRED'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Baby information can no longer be edited once the pregnancy is closed.',
        ], 403);
    }

    $baby->update([
        'first_name' => $request->first_name,
        'middle_name' => $request->middle_name,
        'last_name' => $request->last_name,
        'sex' => $request->sex,
        'date_of_birth' => $request->date_of_birth,
        'time_of_birth' => $request->time_of_birth,
        'birth_weight' => $request->birth_weight,
        'birth_length' => $request->birth_length,
    ]);

    $this->logAction(
        'UPDATE',
        'BABY',
        'Updated baby information: ' . $baby->full_name
    );

    return response()->json([
        'success' => true,
        'baby' => array_merge($baby->toArray(), [
            'birth_weight_display' => WeightFormatter::formatKg($baby->birth_weight),
        ]),
        'message' => 'Baby information updated successfully.'
    ]);
}


}   
