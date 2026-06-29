<?php

namespace App\Http\Controllers;

use App\Models\Baby;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $patients = Patient::where('status', 'ONGOING')->get();

    return view('patients.index', compact('patients'));
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
    $request->validate([
        'first_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:255',
        'middle_name' => 'nullable|regex:/^[a-zA-Z\s]+$/|max:255',
        'last_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:255',

        'birthdate' => 'required|date|before:today',
        'age' => 'required|integer|min:10|max:60',

        'address' => 'required|string|max:255',

        'contact_number' => ['required','regex:/^09\d{9}$/'],
        'email' => 'nullable|email|max:255',
        

        'civil_status' => 'required|in:Single,Married,Widowed',

        'philhealth_member' => 'required|in:0,1',
        'philhealth_number' => 'nullable|required_if:philhealth_member,1|max:255',

        'gravida' => 'required|integer|min:0',
        'para' => 'required|integer|min:0',

        'previous_cs' => 'required|in:0,1',
        'miscarriage' => 'required|integer|min:0',

        'lmp' => 'required|date|before_or_equal:today',
        'edd' => 'required|date|after:lmp',
    ]);

    // LOGIC VALIDATION
    if ($request->para > $request->gravida) {
        return back()->withErrors(['para' => 'Para cannot exceed Gravida'])->withInput();
    }

    if ($request->miscarriage > $request->gravida) {
        return back()->withErrors(['miscarriage' => 'Miscarriage cannot exceed Gravida'])->withInput();
    }

    $patient = Patient::create($request->all());

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
    public function show(string $id)
    {
        $patient = Patient::with(['prenatalVisits','medicalHistory','ultrasounds','birthPlan','babies'])->findOrFail($id);

        // Check for required records before allowing prenatal visit creation
        $hasMedicalHistory = $patient->medicalHistory !== null;
        $hasUltrasound = $patient->ultrasounds()->exists();
        $hasBirthPlan = $patient->birthPlan !== null;
        $canAddPrenatalVisit = $hasMedicalHistory && $hasUltrasound && $hasBirthPlan;

        return view('patients.show', compact('patient', 'hasMedicalHistory', 'hasUltrasound', 'hasBirthPlan', 'canAddPrenatalVisit'));
    }

    public function download(Request $request, string $id)
    {
        $request->validate([
            'format' => 'required|in:pdf,csv',
        ]);

        $patient = Patient::with(['prenatalVisits','medicalHistory','birthPlan','babies'])->findOrFail($id);

        $missingFields = $this->getPatientDownloadMissingFields($patient);

        if (!empty($missingFields)) {
            return response()->json([
                'message' => 'Patient data is incomplete. Please complete required information before downloading.',
                'missing' => $missingFields,
            ], 422);
        }

        if ($request->format === 'csv') {
            return $this->downloadPatientCsv($patient);
        }

        return $this->downloadPatientPdf($patient);
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
        if (!$patient->address) {
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
        if (!$patient->medicalHistory) {
            $missing[] = 'Medical history';
        }
        if ($patient->status !== 'ONGOING' && !$patient->delivery_date) {
            $missing[] = 'Delivery date';
        }

        return $missing;
    }

    private function downloadPatientCsv(Patient $patient)
    {
        $latestVisit = $patient->prenatalVisits->sortByDesc('visit_date')->first();

        $patientInfo = collect([
            'Name' => trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name),
            'Age' => $patient->age,
            'Birthdate' => $patient->birthdate,
            'Address' => $patient->address,
            'Contact Number' => $patient->contact_number,
            'Civil Status' => $patient->civil_status,
            'PhilHealth Member' => $patient->philhealth_member ? 'Yes' : 'No',
            'PhilHealth Number' => $patient->philhealth_number ?: 'N/A',
        ])->map(fn($value, $key) => "$key: $value")->implode("\n");

        $pregnancyInfo = collect([
            'Gravida' => $patient->gravida,
            'Para' => $patient->para,
            'LMP' => $patient->lmp,
            'EDD' => $patient->edd,
            'Pregnancy Status' => $patient->status === 'DELIVERED' ? 'Delivered' : 'Ongoing',
            'Delivery Date' => $patient->delivery_date ?: 'N/A',
        ])->map(fn($value, $key) => "$key: $value")->implode("\n");

        $medicalHistory = collect([
            'Epilepsy' => $patient->medicalHistory->epilepsy ? 'Yes' : 'No',
            'Severe Headache' => $patient->medicalHistory->severe_headache ? 'Yes' : 'No',
            'Visual Disturbance' => $patient->medicalHistory->visual_disturbance ? 'Yes' : 'No',
            'Chest Pain' => $patient->medicalHistory->chest_pain ? 'Yes' : 'No',
            'Shortness of Breath' => $patient->medicalHistory->shortness_breath ? 'Yes' : 'No',
            'Breast Mass' => $patient->medicalHistory->breast_mass ? 'Yes' : 'No',
            'Liver Disease' => $patient->medicalHistory->liver_disease ? 'Yes' : 'No',
            'Smoking' => $patient->medicalHistory->smoking ? 'Yes' : 'No',
            'Allergies' => $patient->medicalHistory->allergies ? 'Yes' : 'No',
            'Drug Intake' => $patient->medicalHistory->drug_intake ? 'Yes' : 'No',
            'STD History' => $patient->medicalHistory->std_history ? 'Yes' : 'No',
        ])->map(fn($value, $key) => "$key: $value")->implode("\n");

        $latestVisitInfo = 'No visit recorded';
        $riskData = 'No risk data available';

        if ($latestVisit) {
            $latestVisitInfo = collect([
                'Visit Date' => $latestVisit->visit_date,
                'Blood Pressure' => $latestVisit->bp_sys . '/' . $latestVisit->bp_dia,
                'Weight' => $latestVisit->weight,
                'Temperature' => $latestVisit->temperature,
                'Gestational Age' => $latestVisit->gestational_age,
                'Assessment' => $latestVisit->assessment,
                'Risk Level' => $latestVisit->risk_level,
                'Risk Factors' => $latestVisit->risk_reasons,
                'Next Visit Date' => $latestVisit->next_visit_date,
            ])->map(fn($value, $key) => "$key: " . ($value ?: 'N/A'))->implode("\n");

            $riskData = collect([
                'Current Risk Level' => $latestVisit->risk_level,
                'Identified Risk Factors' => $latestVisit->risk_reasons ?: 'N/A',
                'Overdue Status' => $latestVisit->next_visit_date && Carbon::parse($latestVisit->next_visit_date)->isPast() ? 'Overdue' : 'On time',
            ])->map(fn($value, $key) => "$key: $value")->implode("\n");
        }

        $csvRows = [];
        $csvRows[] = ['Patient Info', 'Pregnancy Info', 'Medical History', 'Latest Visit', 'Risk Data'];
        $csvRows[] = [$patientInfo, $pregnancyInfo, $medicalHistory, $latestVisitInfo, $riskData];

        // Add baby information for delivered patients
        if ($patient->status === 'DELIVERED' && $patient->babies->count() > 0) {
            $csvRows[] = ['', '', '', '', '']; // Empty row for separation
            $csvRows[] = ['Baby Information', '', '', '', ''];

            foreach ($patient->babies as $index => $baby) {
                $babyNumber = $index + 1;
                $babyInfo = collect([
                    'Baby ' . $babyNumber . ' Name' => $baby->full_name,
                    'Sex' => $baby->sex ?: 'N/A',
                    'Date of Birth' => $baby->date_of_birth ? Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A',
                    'Time of Birth' => $baby->time_of_birth ? Carbon::parse($baby->time_of_birth)->format('g:i A') : 'N/A',
                    'Birth Weight' => $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A',
                    'Birth Length' => $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A',
                ])->map(fn($value, $key) => "$key: $value")->implode("\n");

                $csvRows[] = [$babyInfo, '', '', '', ''];
            }
        }

        $filename = 'patient-' . $patient->id . '-record.csv';
        $handle = fopen('php://memory', 'r+');

        foreach ($csvRows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function downloadPatientPdf(Patient $patient)
    {
        $latestVisit = $patient->prenatalVisits->sortByDesc('visit_date')->first();

        $riskSummary = [
            'currentRiskLevel' => $latestVisit?->risk_level ?: 'N/A',
            'identifiedRiskFactors' => $latestVisit?->risk_reasons ?: 'N/A',
            'overdueStatus' => $latestVisit && $latestVisit->next_visit_date && Carbon::parse($latestVisit->next_visit_date)->isPast() ? 'Overdue' : 'On time',
        ];

        $data = [
            'patient' => $patient,
            'latestVisit' => $latestVisit,
            'riskSummary' => $riskSummary,
        ];

        $pdf = Pdf::loadView('exports.patient-record', $data)->setPaper('letter', 'portrait');

        return $pdf->download('patient-' . $patient->id . '-record.pdf');
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
    $request->validate([
    'first_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:255',
    'middle_name' => 'nullable|regex:/^[a-zA-Z\s]+$/|max:255',
    'last_name' => 'required|regex:/^[a-zA-Z\s]+$/|max:255',

    'birthdate' => 'required|date|before:today',
    'age' => 'required|integer|min:10|max:60',

    'address' => 'required|string|max:255',

    'contact_number' => ['required','regex:/^09\d{9}$/'],
    'email' => 'nullable|email|max:255',

    'civil_status' => 'required|in:Single,Married,Widowed',

    'philhealth_member' => 'required|in:0,1',
    'philhealth_number' => 'nullable|required_if:philhealth_member,1|max:255',

    'gravida' => 'required|integer|min:0',
    'para' => 'required|integer|min:0',

    'previous_cs' => 'required|in:0,1',
    'miscarriage' => 'required|integer|min:0',

    'lmp' => 'required|date|before_or_equal:today',
    'edd' => 'required|date|after:lmp',
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


    $patient->update([
    'first_name'        => $request->first_name,
    'middle_name'       => $request->middle_name,
    'last_name'         => $request->last_name,
    'birthdate'         => $request->birthdate,
    'age'               => $request->age,
    'address'           => $request->address,
    'contact_number'    => $request->contact_number,
    'email'             => $request->email,
    'civil_status'      => $request->civil_status,
    'philhealth_member' => $request->philhealth_member,
    'philhealth_number' => $request->philhealth_number,
    'gravida'           => $request->gravida,
    'para'              => $request->para,
    'previous_cs'       => $request->previous_cs,
    'miscarriage'       => $request->miscarriage,
    'lmp'               => $request->lmp,
    'edd'               => $request->edd,
]);

   



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
    $request->validate([
        'delivery_date' => 'required|date|before_or_equal:today',
        'babies' => 'array|min:1',
        'babies.*.date_of_birth' => 'required|date',
        'babies.*.time_of_birth' => 'required|date_format:H:i',
        'babies.*.first_name' => 'nullable|string|max:255',
        'babies.*.middle_name' => 'nullable|string|max:255',
        'babies.*.last_name' => 'nullable|string|max:255',
        'babies.*.sex' => 'nullable|in:Male,Female',
        'babies.*.birth_weight' => 'nullable|numeric|min:0|max:10',
        'babies.*.birth_length' => 'nullable|numeric|min:0|max:100',
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

    // Update patient status
    $patient->update([
        'status' => 'DELIVERED',
        'delivery_date' => $request->delivery_date
    ]);

    // Create baby records
    foreach ($babiesData as $babyData) {
        Baby::create([
            'patient_id' => $patient->id,
            'first_name' => $babyData['first_name'] ?? null,
            'middle_name' => $babyData['middle_name'] ?? null,
            'last_name' => $babyData['last_name'] ?? null,
            'sex' => $babyData['sex'] ?? null,
            'date_of_birth' => $babyData['date_of_birth'],
            'time_of_birth' => $babyData['time_of_birth'],
            'birth_weight' => $babyData['birth_weight'] ?? null,
            'birth_length' => $babyData['birth_length'] ?? null,
        ]);
    }

    $this->logAction(
        'UPDATE',
        'PATIENT',
        'Marked as delivered with ' . count($babiesData) . ' baby(ies): ' . $patient->first_name . ' ' . $patient->last_name
    );

    return redirect()->route('patients.delivered')
        ->with('success', 'Patient marked as delivered with baby information recorded.');
}
public function delivered()
{
    $patients = Patient::where('status', 'DELIVERED')->latest()->get();

    return view('patients.delivered', compact('patients'));
}

public function updateBaby(Request $request, $id)
{
    $request->validate([
        'first_name' => 'nullable|string|max:255',
        'middle_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'sex' => 'nullable|in:Male,Female',
        'date_of_birth' => 'required|date',
        'time_of_birth' => 'required|date_format:H:i',
        'birth_weight' => 'nullable|numeric|min:0|max:10',
        'birth_length' => 'nullable|numeric|min:0|max:100',
    ]);

    $baby = Baby::findOrFail($id);

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
        'baby' => $baby,
        'message' => 'Baby information updated successfully.'
    ]);
}


}

