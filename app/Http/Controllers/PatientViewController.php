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

        $status = $request->query('status', 'ONGOING');
        $search = trim((string) $request->query('search', ''));

        $query = Patient::query()
            ->when($status === 'REFERRED', function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('referrals', function ($referralQuery) {
                        $referralQuery->where('status', 'Pending');
                    })->orWhere('status', 'REFERRED');
                });
            }, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($status === 'ONGOING', fn ($query) => $query->with('prenatalVisits:id,patient_id,visit_date'))
            ->when($status === 'REFERRED', fn ($query) => $query->with([
                'referrals' => fn ($referralQuery) => $referralQuery->where('status', 'Pending'),
            ]));

        $matchingPatients = $query
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->orderBy('last_name')
            ->orderBy('birthdate')
            ->orderBy('id')
            ->get();

        $sortKeyFor = fn (Patient $patient) => $this->activitySortKey($patient, $status);

        $patients = $matchingPatients
            ->groupBy(fn (Patient $patient) => $this->patientHistoryKey($patient))
            ->map(fn ($group) => $group->sortByDesc($sortKeyFor)->first())
            ->sortByDesc($sortKeyFor)
            ->values();

        return view('patients.view-all-records', compact('patients', 'status', 'search'));
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

    /**
     * Determines the timestamp used to sort a single Patient row within the
     * currently selected status. This is computed per matching row (before
     * grouping by identity) so a person's final position reflects their most
     * recent activity across all of their matching pregnancy rows.
     */
    private function activitySortKey(Patient $patient, string $status): \Illuminate\Support\Carbon
    {
        return match ($status) {
            'ONGOING' => optional($patient->prenatalVisits->sortByDesc('visit_date')->first())->visit_date
                ?? $patient->created_at,
            'DELIVERED' => $patient->delivery_date ?? $patient->created_at,
            'REFERRED' => optional($patient->referrals->sortByDesc('referral_date')->first())->referral_date
                ?? $patient->created_at,
            default => $patient->created_at,
        };
    }

    private function patientHistoryKey(Patient $patient): string
    {
        return strtolower(trim(
            $patient->first_name.'|'.
            $patient->middle_name.'|'.
            $patient->last_name.'|'.
            $patient->birthdate
        ));
    }
}
