<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">Edit Birth Plan</h1>
                <p class="text-sm text-gray-500 mt-1">Update the patient’s birth preparation and support details.</p>
            </div>
            <a href="{{ route('patients.show', $birthPlan->patient_id) }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Patient Profile
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Birth Plan Details</h2>
                <p class="text-sm text-gray-500 mt-1">Edit the patient’s expected birth plan information.</p>
            </div>

            <form action="{{ route('birth-plans.update', $birthPlan->id) }}" method="POST" class="px-6 py-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Planned Prenatal Visits</label>
                        <input type="number" name="planned_visits" value="{{ old('planned_visits', $birthPlan->planned_visits) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Deliver in Clinic</label>
                        <select name="deliver_in_clinic" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('deliver_in_clinic', $birthPlan->deliver_in_clinic) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('deliver_in_clinic', $birthPlan->deliver_in_clinic) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Delivery Location</label>
                        <input type="text" name="delivery_location" value="{{ old('delivery_location', $birthPlan->delivery_location) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Transportation</label>
                        <input type="text" name="transportation" value="{{ old('transportation', $birthPlan->transportation) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Transport Cost</label>
                        <select name="transport_cost" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('transport_cost', $birthPlan->transport_cost) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('transport_cost', $birthPlan->transport_cost) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                        <input type="text" name="payment_method" value="{{ old('payment_method', $birthPlan->payment_method) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Saving Started</label>
                        <select name="saving_started" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('saving_started', $birthPlan->saving_started) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('saving_started', $birthPlan->saving_started) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Birth Companion</label>
                        <input type="text" name="birth_companion" value="{{ old('birth_companion', $birthPlan->birth_companion) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Caregiver at Home</label>
                        <input type="text" name="caregiver_home" value="{{ old('caregiver_home', $birthPlan->caregiver_home) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Plan More Children</label>
                        <select name="plan_more_children" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('plan_more_children', $birthPlan->plan_more_children) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('plan_more_children', $birthPlan->plan_more_children) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Number of More Children</label>
                        <input type="number" name="number_more_children" value="{{ old('number_more_children', $birthPlan->number_more_children) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Knows FP Method</label>
                        <select name="knows_fp_method" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('knows_fp_method', $birthPlan->knows_fp_method) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('knows_fp_method', $birthPlan->knows_fp_method) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Used FP Before</label>
                        <select name="used_fp_before" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                            <option value="1" {{ old('used_fp_before', $birthPlan->used_fp_before) == 1 ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ old('used_fp_before', $birthPlan->used_fp_before) == 0 ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Family Planning Method</label>
                        <input type="text" name="family_planning_method" value="{{ old('family_planning_method', $birthPlan->family_planning_method) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">FP Source</label>
                        <input type="text" name="fp_source" value="{{ old('fp_source', $birthPlan->fp_source) }}" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" rows="4" class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $birthPlan->notes) }}</textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('patients.show', $birthPlan->patient_id) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Update Birth Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>