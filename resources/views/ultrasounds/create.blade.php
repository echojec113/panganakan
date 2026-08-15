<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        <!-- Back Button -->
        <div class="mb-4 sm:mb-6">
            <a href="{{ route('patients.show', $patient_id) }}" class="inline-flex items-center text-gray-600 hover:text-blue-600 transition group">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2 group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Patient Profile
            </a>
        </div>

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-50 border-b border-gray-100 px-4 sm:px-6 py-4 sm:py-5">
                <x-icon-title title="Add Ultrasound Record" subtitle="Record new ultrasound examination results">
                    <x-slot name="icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </x-slot>
                </x-icon-title>
            </div>

            <form action="{{ route('ultrasound.store') }}" method="POST" enctype="multipart/form-data" id="ultrasoundForm" class="p-4 sm:p-6">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient_id }}">

                <!-- Error Summary -->
                <x-error-summary :errors="$errors" title="Please fix the following errors:" class="mb-6" />

                <!-- Scan Information Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Scan Information</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Scan Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="scan_date" id="scan_date" 
                                value="{{ old('scan_date', date('Y-m-d')) }}" 
                                max="{{ date('Y-m-d') }}" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <p class="text-xs text-gray-500 mt-1">Cannot be in the future</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Gestational Age (weeks)
                            </label>
                            <input type="number" name="gestational_age_scan" id="gestational_age" 
                                value="{{ old('gestational_age_scan') }}" 
                                step="0.5" min="4" max="42"
                                placeholder="e.g., 28.5"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <p class="text-xs text-gray-500 mt-1" id="ga_hint">Based on patient's LMP: Will auto-validate</p>
                        </div>
                    </div>
                </div>

                <!-- Fetal Measurements Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Fetal Measurements</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Estimated Fetal Weight (grams)
                            </label>
                            <input type="number" name="estimated_fetal_weight" id="fetal_weight" 
                                value="{{ old('estimated_fetal_weight') }}" 
                                step="10" min="200" max="5000"
                                placeholder="e.g., 1500"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <p class="text-xs text-gray-500 mt-1">Range: 200 - 5000 grams</p>
                        </div>
                    </div>
                </div>

                <!-- Fetal Assessment Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Fetal Assessment</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fetal Heartbeat
                            </label>
                            <select name="fetal_heartbeat" id="fetal_heartbeat" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Normal 120-160" {{ old('fetal_heartbeat') == 'Normal 120-160' ? 'selected' : '' }}>Normal (120-160 bpm)</option>
                                <option value="Tachycardia >160" {{ old('fetal_heartbeat') == 'Tachycardia >160' ? 'selected' : '' }}>Tachycardia (>160 bpm)</option>
                                <option value="Bradycardia <120" {{ old('fetal_heartbeat') == 'Bradycardia <120' ? 'selected' : '' }}>Bradycardia (<120 bpm)</option>
                                <option value="Weak" {{ old('fetal_heartbeat') == 'Weak' ? 'selected' : '' }}>Weak</option>
                                <option value="Absent" {{ old('fetal_heartbeat') == 'Absent' ? 'selected' : '' }}>Absent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fetal Movement
                            </label>
                            <select name="fetal_movement" id="fetal_movement" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Active" {{ old('fetal_movement') == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Normal" {{ old('fetal_movement') == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Decreased" {{ old('fetal_movement') == 'Decreased' ? 'selected' : '' }}>Decreased</option>
                                <option value="Absent" {{ old('fetal_movement') == 'Absent' ? 'selected' : '' }}>Absent</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Presentation
                            </label>
                            <select name="presentation" id="presentation" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Cephalic" {{ old('presentation') == 'Cephalic' ? 'selected' : '' }}>Cephalic (Head down)</option>
                                <option value="Breech" {{ old('presentation') == 'Breech' ? 'selected' : '' }}>Breech</option>
                                <option value="Transverse" {{ old('presentation') == 'Transverse' ? 'selected' : '' }}>Transverse</option>
                                <option value="Oblique" {{ old('presentation') == 'Oblique' ? 'selected' : '' }}>Oblique</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Placenta & Fluid Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Placenta & Amniotic Fluid</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Amniotic Fluid
                            </label>
                            <select name="amniotic_fluid" id="amniotic_fluid" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Normal" {{ old('amniotic_fluid') == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Low" {{ old('amniotic_fluid') == 'Low' ? 'selected' : '' }}>Low (Oligohydramnios)</option>
                                <option value="High" {{ old('amniotic_fluid') == 'High' ? 'selected' : '' }}>High (Polyhydramnios)</option>
                                <option value="Moderate" {{ old('amniotic_fluid') == 'Moderate' ? 'selected' : '' }}>Moderate</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Placenta Position
                            </label>
                            <select name="placenta_position" id="placenta_position" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Anterior" {{ old('placenta_position') == 'Anterior' ? 'selected' : '' }}>Anterior (Front wall)</option>
                                <option value="Posterior" {{ old('placenta_position') == 'Posterior' ? 'selected' : '' }}>Posterior (Back wall)</option>
                                <option value="Fundal" {{ old('placenta_position') == 'Fundal' ? 'selected' : '' }}>Fundal (Top)</option>
                                <option value="Lateral" {{ old('placenta_position') == 'Lateral' ? 'selected' : '' }}>Lateral (Side)</option>
                                <option value="Low-lying" {{ old('placenta_position') == 'Low-lying' ? 'selected' : '' }}>Low-lying</option>
                                <option value="Placenta Previa" {{ old('placenta_position') == 'Placenta Previa' ? 'selected' : '' }}>Placenta Previa</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Report Upload Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Report Upload</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Upload Report (Image or PDF)
                        </label>
                        <div class="flex items-center justify-center w-full">
                            <label id="reportDropZone" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="text-sm text-gray-500">Click to upload or drag and drop</p>
                                    <p class="text-xs text-gray-400 mt-1">PDF, JPG, JPEG, PNG, WebP (Max 5MB)</p>
                                </div>
                                <input type="file" name="report_file" id="report_file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            </label>
                        </div>
                        <div id="imagePreviewWrap" class="hidden mt-3">
                            <img id="imagePreview" alt="Ultrasound image preview" class="max-h-64 max-w-full rounded-lg border border-gray-200">
                        </div>
                        <div id="fileInfo" class="hidden mt-2 p-2 bg-green-50 rounded-lg border border-green-200 text-sm text-green-700">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-medium shrink-0">Selected file:</span>
                                    <span id="fileName" class="truncate"></span>
                                </div>
                                <button type="button" id="removeFileBtn" title="Remove selected file" class="shrink-0 w-6 h-6 flex items-center justify-center rounded-full bg-red-100 text-red-600 hover:bg-red-200 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remarks Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Remarks</h3>
                    </div>
                    <div>
                        <textarea name="remarks" rows="3" 
                            placeholder="Any additional observations, findings, or recommendations..."
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">{{ old('remarks') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Max 1000 characters</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('patients.show', $patient_id) }}" class="order-2 sm:order-1 px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-center">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="order-1 sm:order-2 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm font-medium">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        Save Ultrasound Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var patientLmp = '{{ $patient->lmp ? \Carbon\Carbon::parse($patient->lmp)->format("Y-m-d") : "" }}';

        document.addEventListener('DOMContentLoaded', function() {
            // File upload preview
            const fileInput = document.getElementById('report_file');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewWrap = document.getElementById('imagePreviewWrap');
            const removeFileBtn = document.getElementById('removeFileBtn');
            const reportDropZone = document.getElementById('reportDropZone');

            function clearFileSelection() {
                fileInput.value = '';
                fileName.textContent = '';
                imagePreview.src = '';
                imagePreviewWrap.classList.add('hidden');
                fileInfo.classList.add('hidden');
            }

            function renderSelectedFile(file) {
                if (!file) {
                    clearFileSelection();
                    return;
                }

                const fileSize = (file.size / 1024 / 1024).toFixed(2);

                if (file.type.startsWith('image/')) {
                    fileName.textContent = `${file.name} (${fileSize} MB)`;
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewWrap.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    fileName.textContent = file.name;
                    imagePreviewWrap.classList.add('hidden');
                }
                fileInfo.classList.remove('hidden');
            }

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        renderSelectedFile(this.files[0]);
                    } else {
                        clearFileSelection();
                    }
                });
            }

            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    clearFileSelection();
                });
            }

            if (reportDropZone) {
                reportDropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.add('border-blue-400', 'bg-blue-50');
                });

                reportDropZone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('border-blue-400', 'bg-blue-50');
                });

                reportDropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.classList.remove('border-blue-400', 'bg-blue-50');
                    const files = e.dataTransfer && e.dataTransfer.files;
                    if (files && files.length) {
                        fileInput.files = files;
                        renderSelectedFile(files[0]);
                    }
                });
            }
            
            // Form validation
            const form = document.getElementById('ultrasoundForm');
            const scanDate = document.getElementById('scan_date');
            const gestationalAge = document.getElementById('gestational_age');
            const fetalWeight = document.getElementById('fetal_weight');
            const gaHint = document.getElementById('ga_hint');
            
            function showError(element, message) {
                element.classList.add('border-red-500');
                let errorDiv = element.parentElement.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('p');
                    errorDiv.className = 'error-message text-red-500 text-xs mt-1';
                    element.parentElement.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
            }
            
            function hideError(element) {
                element.classList.remove('border-red-500');
                const errorDiv = element.parentElement.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
            }
            
            // Validate scan date
            if (scanDate) {
                scanDate.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate > today) {
                        showError(this, 'Scan date cannot be in the future');
                    } else {
                        hideError(this);
                    }
                });
            }
            
            // Gestational Age vs LMP validation (matching Prenatal Visit logic)
            function validateGA() {
                const lmp = patientLmp;
                const ga = parseFloat(gestationalAge?.value);
                const scanDateValue = scanDate?.value;

                if (lmp && ga && scanDateValue) {
                    const lmpDate = new Date(lmp);
                    const scanDateObj = new Date(scanDateValue);
                    const diffDays = (scanDateObj - lmpDate) / (1000 * 60 * 60 * 24);
                    const expectedWeeks = diffDays / 7;
                    
                    if (Math.abs(expectedWeeks - ga) > 3) {
                        showError(gestationalAge, `Based on LMP (${lmp}), expected GA is ${expectedWeeks.toFixed(1)} weeks. Current GA should be within ±3 weeks.`);
                        gaHint.innerHTML = `Expected GA: ${expectedWeeks.toFixed(1)} weeks based on LMP`;
                        gaHint.classList.add('text-orange-600');
                        gaHint.classList.remove('text-green-600');
                        return false;
                    } else {
                        hideError(gestationalAge);
                        gaHint.innerHTML = `✅ Based on LMP, expected GA: ${expectedWeeks.toFixed(1)} weeks`;
                        gaHint.classList.remove('text-orange-600');
                        gaHint.classList.add('text-green-600');
                        return true;
                    }
                }
                return true;
            }
            
            // Validate gestational age range
            if (gestationalAge) {
                gestationalAge.addEventListener('input', function() {
                    const ga = parseFloat(this.value);
                    if (ga && (ga < 4 || ga > 42)) {
                        showError(this, 'Gestational age must be between 4 and 42 weeks');
                    } else {
                        hideError(this);
                    }
                    validateGA();
                });
            }
            
            if (scanDate) {
                scanDate.addEventListener('change', function() {
                    validateGA();
                });
            }
            
            // Validate fetal weight range
            if (fetalWeight) {
                fetalWeight.addEventListener('input', function() {
                    const weight = parseFloat(this.value);
                    if (weight && (weight < 200 || weight > 5000)) {
                        showError(this, 'Fetal weight must be between 200 and 5000 grams');
                    } else {
                        hideError(this);
                    }
                });
            }
            
            // Form submission validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validate scan date
                    if (scanDate && scanDate.value) {
                        const selectedDate = new Date(scanDate.value);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        
                        if (selectedDate > today) {
                            showError(scanDate, 'Scan date cannot be in the future');
                            isValid = false;
                        }
                    } else if (scanDate && !scanDate.value) {
                        showError(scanDate, 'Scan date is required');
                        isValid = false;
                    }
                    
                    // Validate gestational age
                    if (gestationalAge && gestationalAge.value) {
                        const ga = parseFloat(gestationalAge.value);
                        if (ga < 4 || ga > 42) {
                            showError(gestationalAge, 'Gestational age must be between 4 and 42 weeks');
                            isValid = false;
                        }
                        if (!validateGA()) {
                            isValid = false;
                        }
                    }
                    
                    // Validate fetal weight
                    if (fetalWeight && fetalWeight.value) {
                        const weight = parseFloat(fetalWeight.value);
                        if (weight < 200 || weight > 5000) {
                            showError(fetalWeight, 'Fetal weight must be between 200 and 5000 grams');
                            isValid = false;
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        const firstError = document.querySelector('.border-red-500');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>