<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Ultrasound;
use App\Models\Patient;
use Carbon\Carbon;
use App\Services\PatientAssessmentRecalculationService;

class UltrasoundController extends Controller
{
    private PatientAssessmentRecalculationService $recalculationService;

    public function __construct(PatientAssessmentRecalculationService $recalculationService)
    {
        $this->recalculationService = $recalculationService;
    }

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
            'report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120', // 5MB
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
            'report_file.mimes' => 'File must be PDF, JPG, JPEG, PNG, or WebP format',
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
        $reportImage = null;
        $reportFile = null;

        if ($request->hasFile('report_file')) {
            $file = $request->file('report_file');
            $storedPath = $this->storeReportFile($request, $patient);

            // Route by MIME type, never by trusting the original filename.
            if ($this->isImageFile($file)) {
                $reportImage = $storedPath;
            } else {
                $reportFile = $storedPath;
            }
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
            'report_image' => $reportImage,
            'report_file' => $reportFile,
            'remarks' => $request->remarks,
        ]);

        // ✅ AUDIT LOG
        $this->logAction(
            'CREATE',
            'ULTRASOUND',
            'Added ultrasound for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        // Auto-recalculate incomplete prenatal visits
        $this->recalculationService->recalculateIncompleteVisits($request->patient_id);

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
            
            'report_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
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
            'report_file.mimes' => 'File must be PDF, JPG, JPEG, PNG, or WebP format',
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
        $replacedImage = false;
        $replacedPdf = false;

        if ($request->hasFile('report_file')) {
            $file = $request->file('report_file');
            $column = $this->isImageFile($file) ? 'report_image' : 'report_file';

            // Delete the previous file of the same type only, keeping the other.
            if ($ultrasound->{$column} && \Storage::disk('public')->exists($ultrasound->{$column})) {
                \Storage::disk('public')->delete($ultrasound->{$column});
            }

            $ultrasound->{$column} = $this->storeReportFile($request, $patient);
            $replacedImage = $column === 'report_image';
            $replacedPdf = $column === 'report_file';
        }

        // ======================
        // MARKED-FOR-REMOVAL HANDLING
        // ======================
        // The edit form marks existing files for removal via remove_image /
        // remove_pdf. The physical file is deleted only here, on a successful
        // save. If a fresh replacement was just uploaded for a type, that
        // upload wins and the X only cancels the already-replaced old file.
        if ($request->boolean('remove_image') && !$replacedImage) {
            $this->deleteUltrasoundFile($ultrasound->report_image);
            $ultrasound->report_image = null;
        }

        if ($request->boolean('remove_pdf') && !$replacedPdf) {
            $this->deleteUltrasoundFile($ultrasound->report_file);
            $ultrasound->report_file = null;
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

        // Auto-recalculate incomplete prenatal visits
        $this->recalculationService->recalculateIncompleteVisits($ultrasound->patient_id);

        return redirect()->route('patients.show', $ultrasound->patient_id)
            ->with('success', 'Ultrasound updated successfully');
    }

    public function destroy($id)
    {
        $ultrasound = Ultrasound::findOrFail($id);
        $patientName = $ultrasound->patient->first_name . ' ' . $ultrasound->patient->last_name;
        
        // Delete files if they exist
        foreach (['report_image', 'report_file'] as $column) {
            $this->deleteUltrasoundFile($ultrasound->{$column});
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

    /**
     * Stream an ultrasound report file (image or PDF) inline to authorized users.
     *
     * The route sits inside the auth group (admin and staff only) and the type
     * whitelist prevents access to arbitrary filesystem paths.
     *
     * @param string $type 'image' | 'pdf'
     */
    public function file($id, string $type)
    {
        if (!in_array($type, ['image', 'pdf'], true)) {
            abort(404);
        }

        $ultrasound = Ultrasound::findOrFail($id);
        $path = $type === 'image' ? $ultrasound->report_image : $ultrasound->report_file;

        if (!$path || !\Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(\Storage::disk('public')->path($path));
    }

    /**
     * Store the uploaded report file under a unique, generated name.
     */
    private function storeReportFile(Request $request, Patient $patient): string
    {
        $file = $request->file('report_file');
        $fileName = time() . '_' . $patient->id . '_' . $request->scan_date . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();

        return $file->storeAs('ultrasounds', $fileName, 'public');
    }

    /**
     * Determine whether an upload is an image by MIME type.
     */
    private function isImageFile(\Illuminate\Http\UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getMimeType(), 'image/');
    }

    /**
     * Delete an ultrasound report file from the public disk.
     *
     * Only paths under the configured "ultrasounds/" folder are accepted, and
     * the caller must pass a path already stored on this record (never a value
     * from the request), so no file outside this record's folder can be removed.
     */
    private function deleteUltrasoundFile(?string $path): void
    {
        if (!$path || !Str::startsWith($path, 'ultrasounds/')) {
            return;
        }

        if (\Storage::disk('public')->exists($path)) {
            \Storage::disk('public')->delete($path);
        }
    }
}