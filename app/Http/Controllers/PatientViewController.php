<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;

class PatientViewController extends Controller
{
    public function index(Request $request)
{
    $this->checkAdmin();

    $request->validate([
        'status' => ['nullable', 'in:ONGOING,DELIVERED,REFERRED'],
        'search' => ['nullable', 'string', 'max:255'],
    ]);

    $status = (string) $request->query('status', '');
    $search = trim((string) $request->query('search', ''));

    // Allow searches such as:
    // PT-0028, PT0028, 28
    $patientId = preg_match('/^(?:PT-?)?0*([0-9]+)$/i', $search, $matches)
        ? (int) $matches[1]
        : null;

    /*
    |--------------------------------------------------------------------------
    | Summary Counts
    |--------------------------------------------------------------------------
    | These count actual non-deleted Patient records.
    | They are NOT grouped by patient identity.
    */

    $totalPatientRecords = Patient::count();

    $ongoingPatientRecords = Patient::where('status', 'ONGOING')->count();

    $deliveredPatientRecords = Patient::where('status', 'DELIVERED')->count();

    $referredPatientRecords = Patient::where('status', 'REFERRED')->count();

$pendingReferralRecords = Patient::query()
    ->whereHas('referrals', function ($query) {
        $query->where('status', 'Pending');
    })
    ->count();

    /*
    |--------------------------------------------------------------------------
    | Patient Records
    |--------------------------------------------------------------------------
    */

    $patients = Patient::query()

        // Needed for latest activity and referred status.
        ->with([
            'prenatalVisits:id,patient_id,visit_date',
            'referrals' => fn ($query) => $query
                ->select('id', 'patient_id', 'referral_date', 'status')
                ->where('status', 'Pending'),
        ])

        // Status filter
        ->when($status === 'ONGOING', function ($query) {
            $query->where('status', 'ONGOING');
        })

        ->when($status === 'DELIVERED', function ($query) {
            $query->where('status', 'DELIVERED');
        })

        ->when($status === 'REFERRED', function ($query) {
    $query->where('status', 'REFERRED');
})

        // Search by patient name or Patient ID
        ->when($search !== '', function ($query) use ($search, $patientId) {
            $query->where(function ($query) use ($search, $patientId) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");

                if ($patientId !== null) {
                    $query->orWhere('id', $patientId);
                }
            });
        })

        // Most recently created patient records first.
        ->orderByDesc('created_at')
        ->orderByDesc('id')

        // Same compact pagination style as Staff Patient Records.
        ->paginate(10)
        ->withQueryString();

    /*
    |--------------------------------------------------------------------------
    | Display-only values
    |--------------------------------------------------------------------------
    */

    $patients->getCollection()->each(function (Patient $patient) {

        /*
         * A pending referral takes precedence for the directory display.
         * Otherwise use the Patient record's stored status.
         */
        $patient->directory_status = $patient->status;
$patient->has_pending_referral = $patient->referrals->isNotEmpty();

        /*
         * Latest relevant activity recorded for this pregnancy record.
         */
        $activities = collect([
            $patient->prenatalVisits->max('visit_date'),
            $patient->delivery_date,
            $patient->referrals->max('referral_date'),
            $patient->created_at,
        ])->filter();

        $patient->directory_activity = $activities
            ->map(fn ($date) => \Carbon\Carbon::parse($date))
            ->sortDesc()
            ->first();
    });

    return view('patients.view-all-records', compact(
    'patients',
    'status',
    'search',
    'totalPatientRecords',
    'ongoingPatientRecords',
    'deliveredPatientRecords',
    'referredPatientRecords',
    'pendingReferralRecords'
));
}
    public function history(Patient $patient)
    {
        $this->checkAdmin();

        $pregnancies = $this->pregnanciesFor($patient)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('patients.view-history', compact('patient', 'pregnancies'));
    }

    public function pregnancy(Patient $patient)
    {
        $this->checkAdmin();

        $this->loadPregnancyRelationships($patient);

        return view('patients.view-pregnancy', compact('patient'));
    }

    public function print(Patient $patient)
    {
        $this->checkAdmin();

        $this->loadPregnancyRelationships($patient);

        return view('patients.print-pregnancy', compact('patient'));
    }

    private function checkAdmin(): void
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            abort(403);
        }
    }

    private function pregnanciesFor(Patient $patient)
    {
        return Patient::query()
            ->where('first_name', $patient->first_name)
            ->where('last_name', $patient->last_name)
            ->when(
                $patient->middle_name !== null,
                fn ($query) => $query->where('middle_name', $patient->middle_name),
                fn ($query) => $query->whereNull('middle_name')
            )
            ->when(
                $patient->birthdate !== null,
                fn ($query) => $query->where('birthdate', $patient->birthdate),
                fn ($query) => $query->whereNull('birthdate')
            );
    }

    private function loadPregnancyRelationships(Patient $patient): void
    {
        $patient->load([
            'prenatalVisits' => fn ($query) => $query->orderBy('visit_date')->orderBy('id'),
            'pregnancyOutcome.confirmedBy',
            'babies',
        ]);
    }

    
}
