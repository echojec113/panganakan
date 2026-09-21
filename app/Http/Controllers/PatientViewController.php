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
        ]);

        $status = $request->query('status', 'ONGOING');

        $matchingPatients = Patient::query()
            ->when($status === 'REFERRED', function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('referrals', function ($referralQuery) {
                        $referralQuery->where('status', 'Pending');
                    })->orWhere('status', 'REFERRED');
                });
            }, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('first_name')
            ->orderBy('middle_name')
            ->orderBy('last_name')
            ->orderBy('birthdate')
            ->orderBy('id')
            ->get();

        $patients = $matchingPatients
            ->unique(fn (Patient $patient) => $this->patientHistoryKey($patient))
            ->values();

        return view('patients.view-all-records', compact('patients', 'status'));
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
