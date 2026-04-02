<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ultrasound;
use App\Models\Patient;
use Carbon\Carbon;

class UltrasoundController extends Controller
{
    public function create($patient_id)
    {
        // Verify patient exists
        $patient = Patient::findOrFail($patient_id);
        
        return view('ultrasounds.create', compact('patient_id', 'patient'));
    }

    public function store(Request $request)
    {
        // ======================
        // ENHANCED VALIDATION
        // ======================
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'scan_date' => 'required|date|before_or_equal:today',
            
            // Fetal Assessment
            'fetal_heartbeat' => 'nullable|in:Normal 120-160,Tachycardia >160,Bradycardia <120,Weak,Absent',
            'fetal_movement' => 'nullable|in:Active,Normal,Decreased,Absent',
            'presentation' => 'nullable|in:Cephalic,Breech,Transverse,Oblique',
            
            // Amniotic & Placenta
            'amniotic_fluid' => 'nullable|in:Normal,Low,High,Moderate',
            'placenta_position' => 'nullable|in:Anterior,Posterior,Fundal,Lateral,Low-lying,Placenta Previa',
            
            // Measurements
            'gestational_age_scan' => 'nullable|numeric|min:4|max:42',
            'estimated_fetal_weight' => 'nullable|numeric|min:200|max:5000',
            
            // File & Remarks
            'report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'remarks' => 'nullable|string|max:1000'
        ], [
            // Custom error messages
            'scan_date.before_or_equal' => 'Scan date cannot be in the future',
            'fetal_heartbeat.in' => 'Please select a valid fetal heartbeat status',
            'fetal_movement.in' => 'Please select a valid fetal movement status',
            'presentation.in' => 'Please select a valid presentation',
            'amniotic_fluid.in' => 'Please select a valid amniotic fluid level',
            'placenta_position.in' => 'Please select a valid placenta position',
            'gestational_age_scan.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age_scan.max' => 'Gestational age cannot exceed 42 weeks',
            'estimated_fetal_weight.min' => 'Estimated fetal weight must be at least 200 grams',
            'estimated_fetal_weight.max' => 'Estimated fetal weight cannot exceed 5000 grams',
            'report_file.max' => 'File size must not exceed 5MB',
            'report_file.mimes' => 'File must be PDF, JPG, JPEG, or PNG format',
            'remarks.max' => 'Remarks cannot exceed 1000 characters'
        ]);

        // ======================
        // LOGICAL VALIDATIONS
        // ======================
        
        // Get patient for validation
        $patient = Patient::find($request->patient_id);
        
        // Validate gestational age vs patient's LMP (if available)
        if ($patient && $patient->lmp && $request->gestational_age_scan) {
            $lmpDate = Carbon::parse($patient->lmp);
            $scanDate = Carbon::parse($request->scan_date);
            $expectedWeeks = $lmpDate->diffInWeeks($scanDate);
            
            if (abs($expectedWeeks - $request->gestational_age_scan) > 3) {
                return back()->withErrors([
                    'gestational_age_scan' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is about {$expectedWeeks} weeks (±3 weeks allowed)."
                ])->withInput();
            }
        }
        
        // Validate fetal weight is reasonable for gestational age
        if ($request->gestational_age_scan && $request->estimated_fetal_weight) {
            $ga = $request->gestational_age_scan;
            $weight = $request->estimated_fetal_weight;
            
            // Simple percentile check (adjustable based on clinical guidelines)
            $expectedWeightMin = 200 + (($ga - 4) * 80); // Rough estimate
            $expectedWeightMax = 200 + (($ga - 4) * 150);
            
            if ($weight < $expectedWeightMin) {
                return back()->withErrors([
                    'estimated_fetal_weight' => "Weight seems low for {$ga} weeks (expected around {$expectedWeightMin}-{$expectedWeightMax}g). Please verify measurements."
                ])->withInput();
            }
            
            if ($weight > $expectedWeightMax) {
                return back()->withErrors([
                    'estimated_fetal_weight' => "Weight seems high for {$ga} weeks (expected around {$expectedWeightMin}-{$expectedWeightMax}g). Please verify measurements."
                ])->withInput();
            }
        }

        // ======================
        // FILE HANDLING
        // ======================
        $filePath = null;

        if ($request->hasFile('report_file')) {
            $file = $request->file('report_file');
            $fileName = time() . '_' . $patient->id . '_' . $request->scan_date . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('ultrasounds', $fileName, 'public');
        }

        // ======================
        // CREATE RECORD
        // ======================
        $ultrasound = Ultrasound::create([
            'patient_id' => $request->patient_id,
            'scan_date' => $request->scan_date,
            'fetal_heartbeat' => $request->fetal_heartbeat,
            'fetal_movement' => $request->fetal_movement,
            'presentation' => $request->presentation,
            'amniotic_fluid' => $request->amniotic_fluid,
            'placenta_position' => $request->placenta_position,
            'gestational_age_scan' => $request->gestational_age_scan,
            'estimated_fetal_weight' => $request->estimated_fetal_weight,
            'report_file' => $filePath,
            'remarks' => $request->remarks,
        ]);

        // ✅ AUDIT LOG
        $this->logAction(
            'CREATE',
            'ULTRASOUND',
            'Added ultrasound for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        return redirect()->route('patients.show', $request->patient_id)
            ->with('success', 'Ultrasound added successfully.');
    }

    public function edit($id)
    {
        $ultrasound = Ultrasound::findOrFail($id);
        $patient = $ultrasound->patient;

        return view('ultrasounds.edit', compact('ultrasound', 'patient'));
    }

    public function update(Request $request, $id)
    {
        $ultrasound = Ultrasound::findOrFail($id);
        $patient = $ultrasound->patient;

        // ======================
        // ENHANCED VALIDATION (Same as store)
        // ======================
        $validated = $request->validate([
            'scan_date' => 'required|date|before_or_equal:today',
            
            'fetal_heartbeat' => 'nullable|in:Normal 120-160,Tachycardia >160,Bradycardia <120,Weak,Absent',
            'fetal_movement' => 'nullable|in:Active,Normal,Decreased,Absent',
            'presentation' => 'nullable|in:Cephalic,Breech,Transverse,Oblique',
            
            'amniotic_fluid' => 'nullable|in:Normal,Low,High,Moderate',
            'placenta_position' => 'nullable|in:Anterior,Posterior,Fundal,Lateral,Low-lying,Placenta Previa',
            
            'gestational_age_scan' => 'nullable|numeric|min:4|max:42',
            'estimated_fetal_weight' => 'nullable|numeric|min:200|max:5000',
            
            'report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'remarks' => 'nullable|string|max:1000'
        ], [
            'scan_date.before_or_equal' => 'Scan date cannot be in the future',
            'fetal_heartbeat.in' => 'Please select a valid fetal heartbeat status',
            'fetal_movement.in' => 'Please select a valid fetal movement status',
            'presentation.in' => 'Please select a valid presentation',
            'amniotic_fluid.in' => 'Please select a valid amniotic fluid level',
            'placenta_position.in' => 'Please select a valid placenta position',
            'gestational_age_scan.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age_scan.max' => 'Gestational age cannot exceed 42 weeks',
            'estimated_fetal_weight.min' => 'Estimated fetal weight must be at least 200 grams',
            'estimated_fetal_weight.max' => 'Estimated fetal weight cannot exceed 5000 grams',
            'report_file.max' => 'File size must not exceed 5MB',
            'remarks.max' => 'Remarks cannot exceed 1000 characters'
        ]);

        // ======================
        // LOGICAL VALIDATIONS
        // ======================
        
        // Validate gestational age vs patient's LMP
        if ($patient && $patient->lmp && $request->gestational_age_scan) {
            $lmpDate = Carbon::parse($patient->lmp);
            $scanDate = Carbon::parse($request->scan_date);
            $expectedWeeks = $lmpDate->diffInWeeks($scanDate);
            
            if (abs($expectedWeeks - $request->gestational_age_scan) > 3) {
                return back()->withErrors([
                    'gestational_age_scan' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is about {$expectedWeeks} weeks."
                ])->withInput();
            }
        }
        
        // Validate fetal weight for gestational age
        if ($request->gestational_age_scan && $request->estimated_fetal_weight) {
            $ga = $request->gestational_age_scan;
            $weight = $request->estimated_fetal_weight;
            
            $expectedWeightMin = 200 + (($ga - 4) * 80);
            $expectedWeightMax = 200 + (($ga - 4) * 150);
            
            if ($weight < $expectedWeightMin) {
                return back()->withErrors([
                    'estimated_fetal_weight' => "Weight seems low for {$ga} weeks (expected around {$expectedWeightMin}-{$expectedWeightMax}g)."
                ])->withInput();
            }
            
            if ($weight > $expectedWeightMax) {
                return back()->withErrors([
                    'estimated_fetal_weight' => "Weight seems high for {$ga} weeks (expected around {$expectedWeightMin}-{$expectedWeightMax}g)."
                ])->withInput();
            }
        }

        // ======================
        // FILE HANDLING
        // ======================
        if ($request->hasFile('report_file')) {
            // Delete old file if exists
            if ($ultrasound->report_file && \Storage::disk('public')->exists($ultrasound->report_file)) {
                \Storage::disk('public')->delete($ultrasound->report_file);
            }
            
            $file = $request->file('report_file');
            $fileName = time() . '_' . $patient->id . '_' . $request->scan_date . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('ultrasounds', $fileName, 'public');
            $ultrasound->report_file = $filePath;
        }

        // ======================
        // UPDATE RECORD
        // ======================
        $ultrasound->update([
            'scan_date' => $request->scan_date,
            'fetal_heartbeat' => $request->fetal_heartbeat,
            'fetal_movement' => $request->fetal_movement,
            'presentation' => $request->presentation,
            'amniotic_fluid' => $request->amniotic_fluid,
            'placenta_position' => $request->placenta_position,
            'gestational_age_scan' => $request->gestational_age_scan,
            'estimated_fetal_weight' => $request->estimated_fetal_weight,
            'remarks' => $request->remarks,
        ]);

        // ✅ AUDIT LOG
        $this->logAction(
            'UPDATE',
            'ULTRASOUND',
            'Updated ultrasound for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        return redirect()->route('patients.show', $ultrasound->patient_id)
            ->with('success', 'Ultrasound updated successfully');
    }

    public function destroy($id)
    {
        $ultrasound = Ultrasound::findOrFail($id);
        $patientName = $ultrasound->patient->first_name . ' ' . $ultrasound->patient->last_name;
        
        // Delete file if exists
        if ($ultrasound->report_file && \Storage::disk('public')->exists($ultrasound->report_file)) {
            \Storage::disk('public')->delete($ultrasound->report_file);
        }
        
        $ultrasound->delete();
        
        // ✅ AUDIT LOG
        $this->logAction(
            'DELETE',
            'ULTRASOUND',
            'Deleted ultrasound for patient: ' . $patientName
        );
        
        return redirect()->route('patients.show', $ultrasound->patient_id)
            ->with('success', 'Ultrasound record deleted successfully');
    }
}