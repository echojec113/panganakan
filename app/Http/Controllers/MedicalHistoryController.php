<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicalHistory;

class MedicalHistoryController extends Controller
{

    public function create(Request $request)
    {
        $patient_id = $request->patient_id;

        return view('medical_histories.create', compact('patient_id'));
    }

    public function store(Request $request)
    {
        $history = MedicalHistory::create([

            'patient_id' => $request->patient_id,

            'epilepsy' => $request->has('epilepsy'),
            'severe_headache' => $request->has('severe_headache'),
            'visual_disturbance' => $request->has('visual_disturbance'),
            'chest_pain' => $request->has('chest_pain'),
            'shortness_breath' => $request->has('shortness_breath'),
            'breast_mass' => $request->has('breast_mass'),
            'liver_disease' => $request->has('liver_disease'),
            'smoking' => $request->has('smoking'),
            'allergies' => $request->has('allergies'),
            'drug_intake' => $request->has('drug_intake'),
            'std_history' => $request->has('std_history'),

        ]);

        // ✅ AUDIT LOG
$this->logAction(
    'CREATE',
    'MEDICAL_HISTORY',
    'Added medical history for patient ID: ' . $request->patient_id
);

        return redirect()->route('patients.show', $request->patient_id);
    }


    public function edit($id)
    {
    $history = MedicalHistory::findOrFail($id);

    return view('medical_histories.edit', compact('history'));
    }

    public function update(Request $request, $id)
{
    $history = MedicalHistory::findOrFail($id);

    // ======================
    // UPDATE BOOLEAN FIELDS
    // ======================
    $history->update([
        'epilepsy' => $request->has('epilepsy'),
        'severe_headache' => $request->has('severe_headache'),
        'visual_disturbance' => $request->has('visual_disturbance'),
        'chest_pain' => $request->has('chest_pain'),
        'shortness_breath' => $request->has('shortness_breath'),
        'breast_mass' => $request->has('breast_mass'),
        'liver_disease' => $request->has('liver_disease'),
        'smoking' => $request->has('smoking'),
        'allergies' => $request->has('allergies'),
        'drug_intake' => $request->has('drug_intake'),
        'std_history' => $request->has('std_history'),
    ]);

    // ✅ AUDIT LOG
$this->logAction(
    'UPDATE',
    'MEDICAL_HISTORY',
    'Updated medical history for patient ID: ' . $history->patient_id
);

    return redirect()->route('patients.show', $history->patient_id)
        ->with('success', 'Medical history updated successfully');
}



}