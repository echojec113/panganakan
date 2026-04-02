<x-app-layout>
    <div class="max-w-5xl mx-auto mt-8 px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <div class="mb-6">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li><a href="{{ route('patients.index') }}" class="text-gray-600 hover:text-blue-600">Patients</a></li>
                    <li><span class="text-gray-400 mx-2">/</span></li>
                    <li><a href="{{ route('patients.show', $patient->id) }}" class="text-gray-600 hover:text-blue-600">{{ $patient->first_name }} {{ $patient->last_name }}</a></li>
                    <li><span class="text-gray-400 mx-2">/</span></li>
                    <li class="text-gray-800 font-medium">Edit</li>
                </ol>
            </nav>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-lg p-2">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-white">Edit Patient</h2>
                        <p class="text-yellow-100 text-sm mt-1">Updating record for {{ $patient->first_name }} {{ $patient->last_name }}</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form action="{{ route('patients.update', $patient->id) }}" method="POST" id="editPatientForm" class="p-6">
                @csrf
                @method('PUT')

                <!-- Error Summary -->
                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="font-semibold text-red-700">Please fix the following errors:</span>
                        </div>
                        <ul class="list-disc list-inside text-sm text-red-600">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- ================= PATIENT INFO ================= -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Patient Information</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name"
                                value="{{ old('first_name', $patient->first_name) }}"
                                pattern="[a-zA-Z\s]+"
                                title="Only letters and spaces are allowed"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('first_name') border-red-500 @enderror"
                                placeholder="Enter first name">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('first_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" name="middle_name"
                                value="{{ old('middle_name', $patient->middle_name) }}"
                                pattern="[a-zA-Z\s]*"
                                title="Only letters and spaces are allowed"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter middle name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name"
                                value="{{ old('last_name', $patient->last_name) }}"
                                pattern="[a-zA-Z\s]+"
                                title="Only letters and spaces are allowed"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('last_name') border-red-500 @enderror"
                                placeholder="Enter last name">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('last_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate <span class="text-red-500">*</span></label>
                            <input type="date" id="birthdate" name="birthdate"
                                value="{{ old('birthdate', $patient->birthdate) }}"
                                max="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('birthdate') border-red-500 @enderror">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('birthdate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Age <span class="text-red-500">*</span></label>
                            <input type="number" id="age" name="age" readonly
                                value="{{ old('age', $patient->age) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Auto-calculated from birthdate</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
                        <input type="text" name="address"
                            value="{{ old('address', $patient->address) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('address') border-red-500 @enderror"
                            placeholder="Enter complete address">
                        <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                        @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                            <input type="tel" id="contact_number" name="contact_number"
                                value="{{ old('contact_number', $patient->contact_number) }}"
                                pattern="09\d{9}"
                                maxlength="11"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('contact_number') border-red-500 @enderror"
                                placeholder="09XXXXXXXXX">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            <p class="text-xs text-gray-500 mt-1">Must start with 09 and be exactly 11 digits</p>
                            @error('contact_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Civil Status <span class="text-red-500">*</span></label>
                            <select name="civil_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('civil_status') border-red-500 @enderror">
                                <option value="">Select Civil Status</option>
                                <option value="Single" {{ old('civil_status', $patient->civil_status) == 'Single' ? 'selected' : '' }}>Single</option>
                                <option value="Married" {{ old('civil_status', $patient->civil_status) == 'Married' ? 'selected' : '' }}>Married</option>
                                <option value="Widowed" {{ old('civil_status', $patient->civil_status) == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                            </select>
                            @error('civil_status')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <!-- ================= PHILHEALTH ================= -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">PhilHealth Information</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PhilHealth Member <span class="text-red-500">*</span></label>
                            <select id="philhealth_member" name="philhealth_member" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="0" {{ old('philhealth_member', $patient->philhealth_member) == '0' ? 'selected' : '' }}>Not a Member</option>
                                <option value="1" {{ old('philhealth_member', $patient->philhealth_member) == '1' ? 'selected' : '' }}>Member</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PhilHealth Number</label>
                            <input type="text" id="philhealth_number" name="philhealth_number"
                                value="{{ old('philhealth_number', $patient->philhealth_number) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                placeholder="Enter PhilHealth number">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('philhealth_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <!-- ================= PREGNANCY INFO ================= -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Pregnancy Information</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gravida <span class="text-red-500">*</span></label>
                            <input type="number" id="gravida" name="gravida"
                                value="{{ old('gravida', $patient->gravida) }}"
                                min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('gravida') border-red-500 @enderror"
                                placeholder="Number of pregnancies">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('gravida')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Para <span class="text-red-500">*</span></label>
                            <input type="number" id="para" name="para"
                                value="{{ old('para', $patient->para) }}"
                                min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('para') border-red-500 @enderror"
                                placeholder="Number of deliveries">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            <p class="text-xs text-gray-500 mt-1">Cannot exceed Gravida</p>
                            @error('para')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Previous CS <span class="text-red-500">*</span></label>
                            <select name="previous_cs" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="0" {{ old('previous_cs', $patient->previous_cs) == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('previous_cs', $patient->previous_cs) == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Menstrual Period <span class="text-red-500">*</span></label>
                            <input type="date" id="lmp" name="lmp"
                                value="{{ old('lmp', $patient->lmp) }}"
                                max="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition @error('lmp') border-red-500 @enderror">
                            <span class="error-message text-red-500 text-xs mt-1 hidden"></span>
                            @error('lmp')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date</label>
                            <input type="date" id="edd" name="edd"
                                value="{{ old('edd', $patient->edd) }}"
                                readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Auto-calculated from LMP (LMP + 280 days)</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Miscarriage History <span class="text-red-500">*</span></label>
                        <select name="miscarriage" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                            <option value="0" {{ old('miscarriage', $patient->miscarriage) == '0' ? 'selected' : '' }}>No</option>
                            <option value="1" {{ old('miscarriage', $patient->miscarriage) == '1' ? 'selected' : '' }}>Yes</option>
                        </select>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('patients.show', $patient->id) }}"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-6 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-lg hover:from-yellow-600 hover:to-orange-600 transition shadow-md font-medium">
                        Update Patient
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showError(element, message) {
        const errorSpan = element.nextElementSibling;
        if (errorSpan && errorSpan.classList.contains('error-message')) {
            errorSpan.textContent = message;
            errorSpan.classList.remove('hidden');
            element.classList.add('border-red-500');
        }
    }
    function hideError(element) {
        const errorSpan = element.nextElementSibling;
        if (errorSpan && errorSpan.classList.contains('error-message')) {
            errorSpan.textContent = '';
            errorSpan.classList.add('hidden');
            element.classList.remove('border-red-500');
        }
    }

    // AGE AUTO CALCULATE
    document.getElementById("birthdate").addEventListener("change", function() {
        let birthdate = new Date(this.value);
        let today = new Date();
        if (isNaN(birthdate.getTime())) { document.getElementById("age").value = ''; return; }
        let age = today.getFullYear() - birthdate.getFullYear();
        let m = today.getMonth() - birthdate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) age--;
        if (age < 10 || age > 60) {
            showError(this, 'Age must be between 10 and 60 years old');
        } else { hideError(this); }
        document.getElementById("age").value = age >= 0 ? age : '';
    });

    // EDD AUTO COMPUTE
    document.getElementById("lmp").addEventListener("change", function() {
        let lmp = new Date(this.value);
        if (isNaN(lmp.getTime())) { document.getElementById("edd").value = ''; return; }
        let edd = new Date(lmp);
        edd.setDate(edd.getDate() + 280);
        document.getElementById("edd").value = edd.toISOString().split('T')[0];
        let today = new Date(); today.setHours(0,0,0,0);
        if (lmp > today) { showError(this, 'LMP cannot be in the future'); } else { hideError(this); }
    });

    // CONTACT VALIDATION
    const contactInput = document.getElementById("contact_number");
    contactInput.addEventListener("input", function() {
        const pattern = /^09\d{9}$/;
        if (this.value.length > 0 && !pattern.test(this.value)) {
            showError(this, 'Must start with 09 and be exactly 11 digits');
        } else if (pattern.test(this.value)) {
            hideError(this);
        } else { hideError(this); }
    });

    // GRAVIDA vs PARA
    const gravidaInput = document.getElementById("gravida");
    const paraInput = document.getElementById("para");
    function validateGravidaPara() {
        let gravida = parseInt(gravidaInput.value || 0);
        let para = parseInt(paraInput.value || 0);
        if (para > gravida) { showError(paraInput, 'Para cannot exceed Gravida'); return false; }
        else { hideError(paraInput); return true; }
    }
    paraInput.addEventListener("input", validateGravidaPara);
    gravidaInput.addEventListener("input", validateGravidaPara);

    // PHILHEALTH TOGGLE
    const philhealthMember = document.getElementById("philhealth_member");
    const philhealthNumber = document.getElementById("philhealth_number");
    function togglePhilHealth() {
        if (philhealthMember.value == "1") {
            philhealthNumber.disabled = false;
            philhealthNumber.required = true;
            philhealthNumber.placeholder = "Enter PhilHealth number *";
        } else {
            philhealthNumber.disabled = true;
            philhealthNumber.required = false;
            philhealthNumber.value = '';
            philhealthNumber.placeholder = "Not required for non-members";
            hideError(philhealthNumber);
        }
    }
    philhealthMember.addEventListener("change", togglePhilHealth);
    togglePhilHealth();

    // NAME FIELDS
    ['first_name','middle_name','last_name'].forEach(field => {
        const input = document.querySelector(`[name="${field}"]`);
        if (input) {
            input.addEventListener("input", function() {
                if (this.value.length > 0 && !/^[a-zA-Z\s]*$/.test(this.value)) {
                    showError(this, 'Only letters and spaces allowed');
                } else { hideError(this); }
            });
        }
    });

    // FORM SUBMIT VALIDATION
    document.getElementById("editPatientForm").addEventListener("submit", function(e) {
        let isValid = true;
        const firstName = document.querySelector('[name="first_name"]');
        if (!firstName.value.trim()) { showError(firstName, 'First name is required'); isValid = false; }
        const lastName = document.querySelector('[name="last_name"]');
        if (!lastName.value.trim()) { showError(lastName, 'Last name is required'); isValid = false; }
        const birthdate = document.getElementById("birthdate");
        if (!birthdate.value) { showError(birthdate, 'Birthdate is required'); isValid = false; } 
        else {
            const age = parseInt(document.getElementById("age").value);
            if (age < 10 || age > 60) { showError(birthdate, 'Age must be between 10 and 60'); isValid = false; }
        }
        const address = document.querySelector('[name="address"]');
        if (!address.value.trim()) { showError(address, 'Address is required'); isValid = false; }
        if (!/^09\d{9}$/.test(contactInput.value)) { showError(contactInput, 'Must start with 09 and be exactly 11 digits'); isValid = false; }
        const gravida = document.getElementById("gravida");
        if (gravida.value === '' || parseInt(gravida.value) < 0) { showError(gravida, 'Gravida is required and must be 0 or greater'); isValid = false; }
        if (!validateGravidaPara()) isValid = false;
        const lmp = document.getElementById("lmp");
        if (!lmp.value) { showError(lmp, 'LMP is required'); isValid = false; }
        if (philhealthMember.value == "1" && philhealthNumber.value.trim() === '') {
            showError(philhealthNumber, 'PhilHealth number is required for members'); isValid = false;
        }
        if (!isValid) {
            e.preventDefault();
            const firstError = document.querySelector('.border-red-500');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    </script>
</x-app-layout>