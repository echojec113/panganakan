<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;

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
        $patient = \App\Models\Patient::with(['prenatalVisits','medicalHistory','ultrasounds','birthPlan'])->findOrFail($id);

        return view('patients.show', compact('patient'));
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

    $request->validate([
        'delivery_date' => 'required|date|before_or_equal:today'
    ]);

    $patient->update([
        'status' => 'DELIVERED',
        'delivery_date' => $request->delivery_date
    ]);

    $this->logAction(
        'UPDATE',
        'PATIENT',
        'Marked as delivered: ' . $patient->first_name . ' ' . $patient->last_name
    );

    return redirect()->route('patients.delivered')
        ->with('success', 'Patient marked as delivered.');
}
public function delivered()
{
    $patients = Patient::where('status', 'DELIVERED')->latest()->get();

    return view('patients.delivered', compact('patients'));
}


}

