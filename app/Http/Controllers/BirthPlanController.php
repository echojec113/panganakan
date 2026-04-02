<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BirthPlan;
use App\Models\Patient;

class BirthPlanController extends Controller
{

    public function index()
    {
        $birthPlans = BirthPlan::with('patient')->get();

        return view('birth_plans.index', compact('birthPlans'));
    }

    public function create(Request $request)
    {
        $patients = Patient::all();
        $selectedPatient = $request->patient_id;

        return view('birth_plans.create', compact('patients','selectedPatient'));
    }

    public function store(Request $request)
    {

        $request->validate([

            'patient_id' => 'required',

            'planned_visits' => 'nullable|numeric',

            'deliver_in_clinic' => 'required',

            'delivery_location' => 'nullable|string',

            'transportation' => 'nullable|string',

            'transport_cost' => 'required',

            'payment_method' => 'nullable|string',

            'saving_started' => 'required',

            'birth_companion' => 'nullable|string',

            'caregiver_home' => 'nullable|string',

            'plan_more_children' => 'required',

            'number_more_children' => 'nullable|numeric',

            'knows_fp_method' => 'required',

            'used_fp_before' => 'required',

            'family_planning_method' => 'nullable|string',

            'fp_source' => 'nullable|string',

            'notes' => 'nullable|string'

        ]);

        $birthPlan = BirthPlan::create([

            'patient_id' => $request->patient_id,

            'planned_visits' => $request->planned_visits,

            'deliver_in_clinic' => $request->deliver_in_clinic,

            'delivery_location' => $request->delivery_location,

            'transportation' => $request->transportation,

            'transport_cost' => $request->transport_cost,

            'payment_method' => $request->payment_method,

            'saving_started' => $request->saving_started,

            'birth_companion' => $request->birth_companion,

            'caregiver_home' => $request->caregiver_home,

            'plan_more_children' => $request->plan_more_children,

            'number_more_children' => $request->number_more_children,

            'knows_fp_method' => $request->knows_fp_method,

            'used_fp_before' => $request->used_fp_before,

            'family_planning_method' => $request->family_planning_method,

            'fp_source' => $request->fp_source,

            'notes' => $request->notes

        ]);

        // ✅ AUDIT LOG
$this->logAction(
    'CREATE',
    'BIRTH_PLAN',
    'Created birth plan for patient ID: ' . $request->patient_id
);



        return redirect()->route('patients.show',$request->patient_id)
            ->with('success','Birth plan saved successfully');

    }


    public function edit($id)
    {
        $birthPlan = BirthPlan::findOrFail($id);

        return view('birth_plans.edit', compact('birthPlan'));
    }


    public function update(Request $request, $id)
{
    $birthPlan = BirthPlan::findOrFail($id);

    // ======================
    // VALIDATION
    // ======================
    $request->validate([
        'planned_visits' => 'nullable|numeric',
        'deliver_in_clinic' => 'required|numeric',
        'delivery_location' => 'nullable|string',
        'transportation' => 'nullable|string',
        'transport_cost' => 'required|numeric',
        'payment_method' => 'nullable|string',
        'saving_started' => 'required|numeric',
        'birth_companion' => 'nullable|string',
        'caregiver_home' => 'nullable|string',
        'plan_more_children' => 'required|numeric',
        'number_more_children' => 'nullable|numeric',
        'knows_fp_method' => 'required|numeric',
        'used_fp_before' => 'required|numeric',
        'family_planning_method' => 'nullable|string',
        'fp_source' => 'nullable|string',
        'notes' => 'nullable|string'
    ]);

    // ======================
    // UPDATE
    // ======================
    $birthPlan->update([
        'planned_visits' => $request->planned_visits,
        'deliver_in_clinic' => $request->deliver_in_clinic,
        'delivery_location' => $request->delivery_location,
        'transportation' => $request->transportation,
        'transport_cost' => $request->transport_cost,
        'payment_method' => $request->payment_method,
        'saving_started' => $request->saving_started,
        'birth_companion' => $request->birth_companion,
        'caregiver_home' => $request->caregiver_home,
        'plan_more_children' => $request->plan_more_children,
        'number_more_children' => $request->number_more_children,
        'knows_fp_method' => $request->knows_fp_method,
        'used_fp_before' => $request->used_fp_before,
        'family_planning_method' => $request->family_planning_method,
        'fp_source' => $request->fp_source,
        'notes' => $request->notes
    ]);

    // ✅ AUDIT LOG
$this->logAction(
    'UPDATE',
    'BIRTH_PLAN',
    'Updated birth plan for patient ID: ' . $birthPlan->patient_id
);



    return redirect()->route('patients.show', $birthPlan->patient_id)
        ->with('success','Birth plan updated successfully');
}


    public function destroy($id)
{
    $birthPlan = BirthPlan::findOrFail($id);

    $patient_id = $birthPlan->patient_id;

    $birthPlan->delete();

    // ✅ AUDIT LOG
    $this->logAction(
        'DELETE',
        'BIRTH_PLAN',
        'Deleted birth plan for patient ID: ' . $patient_id
    );

    return redirect()->route('patients.show',$patient_id)
        ->with('success','Birth plan deleted');
}
}