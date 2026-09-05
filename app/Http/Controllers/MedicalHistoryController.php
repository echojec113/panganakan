<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Services\PatientAssessmentRecalculationService;

class MedicalHistoryController extends Controller
{
    /**
     * Boolean condition fields stored on a Medical History record.
     *
     * This record stores pregnancy-level background documentation. Diabetes
     * and anemia are also assessed during each prenatal visit, and a
     * confirmed Yes on a visit updates this background record. The record
     * itself is not directly submitted to the risk engine; the dated
     * prenatal visit is the source of truth for the visit assessment.
     */
    private const CONDITION_FIELDS = [
        'epilepsy',
        'severe_headache',
        'visual_disturbance',
        'chest_pain',
        'shortness_breath',
        'breast_mass',
        'liver_disease',
        'smoking',
        'allergies',
        'drug_intake',
        'std_history',
        'diabetes',
        'hypertension',
        'asthma',
        'thyroid_disease',
        'heart_disease',
        'anemia',
        'mental_health_condition',
    ];

    private PatientAssessmentRecalculationService $recalculationService;

    public function __construct(PatientAssessmentRecalculationService $recalculationService)
    {
        $this->recalculationService = $recalculationService;
    }

    public function create(Request $request)
    {
        $patient_id = $request->patient_id;

        $patient = Patient::findOrFail($patient_id);
        if ($patient->isDelivered()) {
            return redirect()->route('patients.show', $patient_id)
                ->with('error', 'Medical history cannot be modified for a delivered patient.');
        }

        $existing = MedicalHistory::where('patient_id', $patient_id)->first();
        if ($existing) {
            return redirect()->route('medical-histories.edit', $existing->id)
                ->with('info', 'A Medical History record already exists for this pregnancy.');
        }

        return view('medical_histories.create', compact('patient_id'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules(true));

        $patient = Patient::findOrFail($validated['patient_id']);
        if ($patient->isDelivered()) {
            return redirect()->route('patients.show', $patient->id)
                ->with('error', 'Medical history cannot be modified for a delivered patient.');
        }

        $existing = MedicalHistory::where('patient_id', $validated['patient_id'])->first();
        if ($existing) {
            return redirect()->route('medical-histories.edit', $existing->id)
                ->with('info', 'A Medical History record already exists for this pregnancy.');
        }

        $history = MedicalHistory::create($this->buildData($request, $validated['patient_id']));

        // ✅ AUDIT LOG
        $this->logAction(
            'CREATE',
            'MEDICAL_HISTORY',
            'Added medical history for patient ID: ' . $validated['patient_id']
        );

        // Recalculate only ASSESSMENT INCOMPLETE visits; finalized visits are preserved.
        $this->recalculationService->recalculateIncompleteVisits($validated['patient_id']);

        return redirect()->route('patients.show', $validated['patient_id']);
    }

    public function edit($id)
    {
        $history = MedicalHistory::findOrFail($id);

        $patient = Patient::findOrFail($history->patient_id);
        if ($patient->isDelivered()) {
            return redirect()->route('patients.show', $history->patient_id)
                ->with('error', 'Medical history cannot be modified for a delivered patient.');
        }

        return view('medical_histories.edit', compact('history'));
    }

    public function update(Request $request, $id)
    {
        $history = MedicalHistory::findOrFail($id);

        $patient = Patient::findOrFail($history->patient_id);
        if ($patient->isDelivered()) {
            return redirect()->route('patients.show', $history->patient_id)
                ->with('error', 'Medical history cannot be modified for a delivered patient.');
        }

        $request->validate($this->validationRules(false));

        // Preserve the record's own patient_id; it is never changed on update.
        $history->update($this->buildData($request, $history->patient_id));

        // ✅ AUDIT LOG
        $this->logAction(
            'UPDATE',
            'MEDICAL_HISTORY',
            'Updated medical history for patient ID: ' . $history->patient_id
        );

        // Recalculate only ASSESSMENT INCOMPLETE visits; finalized visits are preserved.
        $this->recalculationService->recalculateIncompleteVisits($history->patient_id);

        return redirect()->route('patients.show', $history->patient_id)
            ->with('success', 'Medical history updated successfully');
    }

    /**
     * Validation rules for store/update.
     *
     * @param bool $requiresPatientId Whether patient_id must be supplied (store only).
     * @return array
     */
    private function validationRules(bool $requiresPatientId): array
    {
        $rules = [];

        if ($requiresPatientId) {
            $rules['patient_id'] = 'required|exists:patients,id';
        }

        foreach (self::CONDITION_FIELDS as $field) {
            $rules[$field] = 'sometimes|boolean';
        }

        $rules['other'] = 'sometimes|boolean';
        $rules['other_specify'] = 'nullable|string|max:255|required_if:other,1';

        return $rules;
    }

    /**
     * Build the fillable payload from the validated request.
     * Unchecked checkboxes are normalized to false; 'other_specify'
     * is stored only when the 'other' checkbox is checked.
     *
     * @param Request $request
     * @param int $patientId
     * @return array
     */
    private function buildData(Request $request, int $patientId): array
    {
        $data = [
            'patient_id' => $patientId,
            'other_specify' => $request->boolean('other') ? ($request->input('other_specify') ?: null) : null,
        ];

        foreach (self::CONDITION_FIELDS as $field) {
            $data[$field] = $request->boolean($field);
        }

        return $data;
    }
}
