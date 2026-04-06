# Medical History Feature - Complete Implementation Analysis

## Overview
The Medical History feature is part of the maternity management system, tracking 11 boolean conditions for each patient. It allows the creation, viewing, and editing of medical risk factors and history data.

---

## 1. Database Layer

### Migration File
**Path:** [database/migrations/2026_03_09_140841_create_medical_histories_table.php](database/migrations/2026_03_09_140841_create_medical_histories_table.php)

**Schema:**
```php
Schema::create('medical_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained()->onDelete('cascade');
    
    // 11 Boolean conditions (all default to false)
    $table->boolean('epilepsy')->default(false);
    $table->boolean('severe_headache')->default(false);
    $table->boolean('visual_disturbance')->default(false);
    $table->boolean('chest_pain')->default(false);
    $table->boolean('shortness_breath')->default(false);
    $table->boolean('breast_mass')->default(false);
    $table->boolean('liver_disease')->default(false);
    $table->boolean('smoking')->default(false);
    $table->boolean('allergies')->default(false);
    $table->boolean('drug_intake')->default(false);
    $table->boolean('std_history')->default(false);
    
    $table->timestamps();
    $table->softDeletes(); // (via SoftDeletes trait)
});
```

**Key Features:**
- Cascade delete when patient is deleted
- All conditions default to `false`
- Soft deletes enabled via model trait
- Foreign key constraint with patient table

---

## 2. Model Layer

### MedicalHistory Model
**Path:** [app/Models/MedicalHistory.php](app/Models/MedicalHistory.php)

```php
class MedicalHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
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
        'std_history'
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
```

### Patient Model
**Path:** [app/Models/Patient.php](app/Models/Patient.php#L73-L77)

```php
public function medicalHistory(): HasOne
{
    return $this->hasOne(MedicalHistory::class);
}
```

**Relationship Type:** `HasOne` - Each patient can have at most one medical history record

**Note:** Cascade soft/hard delete is handled in the Patient model's `boot()` method:
```php
if ($patient->medicalHistory) {
    $patient->medicalHistory->delete();
}
```

---

## 3. Controller Layer

### MedicalHistoryController
**Path:** [app/Http/Controllers/MedicalHistoryController.php](app/Http/Controllers/MedicalHistoryController.php)

#### `create()` Method
```php
public function create(Request $request)
{
    $patient_id = $request->patient_id;
    return view('medical_histories.create', compact('patient_id'));
}
```
- Receives patient_id from query string
- Renders form to create new medical history for a patient

#### `store()` Method
```php
public function store(Request $request)
{
    $history = MedicalHistory::create([
        'patient_id' => $request->patient_id,
        'epilepsy' => $request->has('epilepsy'),
        'severe_headache' => $request->has('severe_headache'),
        // ... 9 more conditions
        'std_history' => $request->has('std_history'),
    ]);
    
    // Logs audit entry
    $this->logAction('CREATE', 'MEDICAL_HISTORY', ...);
    
    return redirect()->route('patients.show', $request->patient_id);
}
```

**Key Logic:**
- Uses `$request->has()` to check if checkbox was submitted
- Converts checkbox presence to boolean (1 or 0)
- Logs action for audit trail
- Redirects back to patient profile

#### `edit()` Method
```php
public function edit($id)
{
    $history = MedicalHistory::findOrFail($id);
    return view('medical_histories.edit', compact('history'));
}
```
- Loads existing medical history record for editing

#### `update()` Method
```php
public function update(Request $request, $id)
{
    $history = MedicalHistory::findOrFail($id);
    
    $history->update([
        'epilepsy' => $request->has('epilepsy'),
        // ... all 11 conditions
    ]);
    
    $this->logAction('UPDATE', 'MEDICAL_HISTORY', ...);
    
    return redirect()->route('patients.show', $history->patient_id)
        ->with('success', 'Medical history updated successfully');
}
```

---

## 4. View Layer

### Display in Patient Profile
**Path:** [resources/views/patients/show.blade.php](resources/views/patients/show.blade.php#L244-L290)

**Section:** Medical History Card (Left Column)

```blade
<!-- Medical History -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-red-600"><!-- Medical icon --></svg>
                <h2 class="text-lg font-semibold text-gray-800">Medical History</h2>
            </div>
            @if($patient->medicalHistory)
                <a href="{{ route('medical-histories.edit', $patient->medicalHistory->id) }}" 
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</a>
            @else
                <a href="{{ route('medical-histories.create', ['patient_id' => $patient->id]) }}"
                   class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg">
                    Add Medical History
                </a>
            @endif
        </div>
    </div>
    
    <div class="p-6">
        @if($patient->medicalHistory)
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @php
                $conditions = [
                    'epilepsy' => 'Epilepsy',
                    'severe_headache' => 'Severe Headache',
                    'visual_disturbance' => 'Visual Disturbance',
                    'chest_pain' => 'Chest Pain',
                    'shortness_breath' => 'Shortness of Breath',
                    'breast_mass' => 'Breast Mass',
                    'liver_disease' => 'Liver Disease',
                    'smoking' => 'Smoking',
                    'allergies' => 'Allergies',
                    'drug_intake' => 'Drug Intake',
                    'std_history' => 'STD History'
                ];
            @endphp
            @foreach($conditions as $field => $label)
                @if($patient->medicalHistory->$field)
                    {{-- MARKED CONDITION - RED X --}}
                    <div class="flex items-center space-x-2 p-2 bg-red-50 rounded-lg border border-red-100">
                        <svg class="w-4 h-4 text-red-600"><!-- X icon --></svg>
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </div>
                @else
                    {{-- UNMARKED CONDITION - GREEN CHECK (FADED) --}}
                    <div class="flex items-center space-x-2 p-2 bg-green-50 rounded-lg border border-green-100 opacity-60">
                        <svg class="w-4 h-4 text-green-600"><!-- Check icon --></svg>
                        <span class="text-sm text-gray-500">{{ $label }}</span>
                    </div>
                @endif
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-4">No medical history recorded</p>
        @endif
    </div>
</div>
```

**Visual Logic:**
- **Selected conditions** (true): Red box with X icon, full opacity
- **Unselected conditions** (false): Green box with checkmark, 60% opacity (faded)
- Grid: 2 columns on mobile, 3 columns on tablet/desktop
- Shows "Add Medical History" button if none exists
- Shows "Edit" link if history exists

### Create Form
**Path:** [resources/views/medical_histories/create.blade.php](resources/views/medical_histories/create.blade.php)

```blade
<form method="POST" action="{{ route('medical-histories.store') }}" class="px-6 py-6 space-y-6">
    @csrf
    <input type="hidden" name="patient_id" value="{{ $patient_id }}">

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($fields as $name => $label)
            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-2xl cursor-pointer hover:border-blue-300 transition">
                <input type="checkbox" name="{{ $name }}" value="1" 
                       class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                <span class="text-sm text-gray-700">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</form>
```

### Edit Form
**Path:** [resources/views/medical_histories/edit.blade.php](resources/views/medical_histories/edit.blade.php)

```blade
<form action="{{ route('medical-histories.update', $history->id) }}" method="POST" class="px-6 py-6 space-y-6">
    @csrf
    @method('PUT')

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($fields as $name => $label)
            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-2xl cursor-pointer hover:border-blue-300 transition">
                <input type="checkbox" name="{{ $name }}" value="1" 
                       {{ $history->$name ? 'checked' : '' }} 
                       class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                <span class="text-sm text-gray-700">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</form>
```

**Key Difference from Create:**
- Adds `{{ $history->$name ? 'checked' : '' }}` to preserve existing selections
- Dynamic field evaluation checks current model state

---

## 5. Routing

**Path:** [routes/web.php](routes/web.php#L48)

```php
Route::resource('medical-histories', MedicalHistoryController::class);
```

**Routes Generated:**
| Method | Route | Controller Action | Purpose |
|--------|-------|-------------------|---------|
| GET | `/medical-histories/create?patient_id=N` | `create()` | Show form to create |
| POST | `/medical-histories` | `store()` | Save new record |
| GET | `/medical-histories/{id}/edit` | `edit()` | Show edit form |
| PUT/PATCH | `/medical-histories/{id}` | `update()` | Save changes |

**Note:** Show and destroy actions are implied but not explicitly used in the UI

---

## 6. Data Flow Diagram

```
PATIENT PROFILE (patients.show)
    ↓
    ├─→ Patient Model (loaded with medicalHistory relationship)
    │   ↓
    │   MedicalHistory Model (11 boolean fields)
    │
    └─→ Display Logic:
        ├─ No medical history? → "Add Medical History" button
        │   └─ Links to: medical-histories.create?patient_id=X
        │       └─ Form with 11 checkboxes
        │           └─ POST to medical-histories.store
        │               └─ Controller converts checkbox to boolean
        │                   └─ Saves to database (soft deleted)
        │                       └─ Redirect back to patients.show
        │
        └─ Medical history exists? → Display grid:
            ├─ For each condition:
            │   ├─ If true:  Red box + X icon + full opacity
            │   └─ If false: Green box + checkmark + faded
            └─ "Edit" link → medical-histories.edit/:id
                └─ Form with checked state preserved
                    └─ PUT to medical-histories.update/:id
                        └─ Controller updates all fields
                            └─ Redirect back to patients.show
```

---

## 7. PatientController Integration

**Path:** [app/Http/Controllers/PatientController.php](app/Http/Controllers/PatientController.php#L107-L110)

```php
public function show(string $id)
{
    $patient = Patient::with(['prenatalVisits','medicalHistory','ultrasounds','birthPlan'])->findOrFail($id);
    return view('patients.show', compact('patient'));
}
```

**Eager Loading:** The `medicalHistory` relationship is eager-loaded to prevent N+1 queries

---

## 8. Issues & Peculiarities

### ✅ Working Correctly
1. **Cascade Soft Delete:** Medical history is soft-deleted when patient is soft-deleted
2. **Cascade Hard Delete:** Medical history is force-deleted when patient is force-deleted
3. **Cascade Restore:** Medical history is restored when patient is restored
4. **Checkbox to Boolean Conversion:** Using `$request->has()` properly converts checkboxes to booleans
5. **Dynamic Field Display:** Blade template correctly evaluates each condition
6. **Audit Logging:** All create/update actions are logged

### ⚠️ Potential Issues

#### Issue 1: Missing Unchecked Checkbox Handling in HTML Forms
**Problem:** When a checkbox is unchecked and form is submitted, HTML does not send that field at all. The current code handles this with `$request->has()`:
- `$request->has('epilepsy')` returns `true` if checkbox exists (even if value is 1)
- `$request->has('epilepsy')` returns `false` if checkbox doesn't exist

**This works, but could be clearer.**

#### Issue 2: No Validation in Medical History Store/Update
**Missing:** No input validation beyond the `$request->has()` check
```php
// Currently
$history = MedicalHistory::create([
    'patient_id' => $request->patient_id,  // ← No validation!
    'epilepsy' => $request->has('epilepsy'),
    // ...
]);
```

**Recommendation:**
```php
$request->validate([
    'patient_id' => 'required|exists:patients,id',
    // Optionally validate all checkbox fields exist
]);
```

#### Issue 3: Edit Form Dynamic Field Access
**Pattern Used:** `$history->$field`
```blade
{{ $history->$name ? 'checked' : '' }}
```

**Risk:** If model doesn't have the property, this silently fails. Better to use:
```blade
{{ isset($history->{$name}) && $history->{$name} ? 'checked' : '' }}
```

However, since all fields are in `$fillable`, this is safe.

#### Issue 4: No Delete Action in UI
**Observation:** The controller doesn't define a `destroy()` method, so medical history records can't be deleted from the UI (only soft-deleted via patient cascade). This matches the design pattern (no standalone delete UI).

#### Issue 5: Patient ID Not Shown in Create/Edit Forms
**Minor UX Issue:** The patient_id is hidden in `<input type="hidden">`, so user can't confirm which patient they're editing for.

**Suggestion:** Add patient name or ID in the form header.

---

## 9. Summary of Files Involved

| File Type | File Path | Purpose |
|-----------|-----------|---------|
| **Model** | [app/Models/MedicalHistory.php](app/Models/MedicalHistory.php) | Medical history data model |
| **Model** | [app/Models/Patient.php](app/Models/Patient.php#L73-L77) | Patient → MedicalHistory relationship |
| **Migration** | [database/migrations/2026_03_09_140841_create_medical_histories_table.php](database/migrations/2026_03_09_140841_create_medical_histories_table.php) | Database schema |
| **Controller** | [app/Http/Controllers/MedicalHistoryController.php](app/Http/Controllers/MedicalHistoryController.php) | CRUD operations |
| **View - Display** | [resources/views/patients/show.blade.php](resources/views/patients/show.blade.php#L244-L290) | Shows medical history in profile |
| **View - Create** | [resources/views/medical_histories/create.blade.php](resources/views/medical_histories/create.blade.php) | Form to add medical history |
| **View - Edit** | [resources/views/medical_histories/edit.blade.php](resources/views/medical_histories/edit.blade.php) | Form to edit medical history |
| **Routes** | [routes/web.php](routes/web.php#L48) | RESTful resource routes |

---

## 10. Conditions Tracked (11 Total)

| Database Column | Display Name | Type | Default |
|-----------------|--------------|------|---------|
| epilepsy | Epilepsy | Boolean | false |
| severe_headache | Severe Headache | Boolean | false |
| visual_disturbance | Visual Disturbance | Boolean | false |
| chest_pain | Chest Pain | Boolean | false |
| shortness_breath | Shortness of Breath | Boolean | false |
| breast_mass | Breast Mass | Boolean | false |
| liver_disease | Liver Disease | Boolean | false |
| smoking | Smoking | Boolean | false |
| allergies | Allergies | Boolean | false |
| drug_intake | Drug Intake | Boolean | false |
| std_history | STD History | Boolean | false |

---

## 11. Testing Checklist

- [ ] Create medical history → Appears in profile with correct conditions marked
- [ ] Edit medical history → Checkboxes reflect current state
- [ ] Toggle conditions → Changes persist in database
- [ ] Delete patient → Medical history cascade deletes
- [ ] Restore patient → Medical history cascade restores
- [ ] Soft delete patient → Medical history is soft-deleted
- [ ] Force delete patient → Medical history is force-deleted
- [ ] Audit logs → All create/update/delete actions logged

