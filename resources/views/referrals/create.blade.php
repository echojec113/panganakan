<x-app-layout>

@php
    $linked = $linkedVisit && is_array($snapshot);
    $snapshotHas = is_array($snapshot) && count($snapshot) > 0;
    $observedContextLabels = [
        'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
        'ultrasound_inputs.presentation' => 'Fetal presentation',
    ];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Create Referral</h1>
        <p class="mt-1 text-sm text-gray-500">Refer {{ $patient->first_name }} {{ $patient->last_name }} to a hospital or OB-GYN specialist.</p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="font-semibold">Please review the highlighted issues.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($linked)
        {{-- Linked referral: two-column desktop layout --}}
        <div class="flex flex-col lg:flex-row gap-6">
            {{-- LEFT: Referral form --}}
            <div class="lg:w-3/5">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Referral Form</h2>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('referrals.store') }}" method="POST">
                            @csrf

                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            @if($linkedVisit)
                            <input type="hidden" name="prenatal_visit_id" value="{{ $linkedVisit->id }}">
                            @endif

                            <div class="mb-5">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Facility</label>
                                <input type="text" name="referred_to" placeholder="e.g., Provincial Hospital, OB-GYN Clinic"
                                    value="{{ old('referred_to') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="mb-5">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Doctor (Optional)</label>
                                <input type="text" name="doctor_name" placeholder="Name of referring doctor"
                                    value="{{ old('doctor_name') }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="mb-5">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason for Referral <span class="text-red-500">*</span></label>
                                <textarea name="reason" rows="4" placeholder="Example: Severe hypertension, breech presentation, high-risk pregnancy..."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y">{{ old('reason', $reasonPrefill ?? '') }}</textarea>
                            </div>

                            <div class="mb-5">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes (Optional)</label>
                                <textarea name="notes" rows="3" placeholder="Any additional remarks..."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y">{{ old('notes') }}</textarea>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Referral Date <span class="text-red-500">*</span></label>
                                <input type="date" name="date_referred" value="{{ old('date_referred', \Carbon\Carbon::today()->toDateString()) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="flex gap-3">
                                <a href="{{ route('patients.show', $patient->id) }}"
                                    class="flex-1 text-center px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-sm">
                                    Back
                                </a>
                                <button type="submit"
                                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold text-sm">
                                    Save Referral
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Assessment Being Referred (read-only) --}}
            <div class="lg:w-2/5">
                <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="border-b border-indigo-100 px-6 py-4 bg-indigo-50">
                        <h2 class="text-lg font-semibold text-indigo-900">Assessment Being Referred</h2>
                        <p class="mt-0.5 text-xs text-indigo-600">Linked Assessment Evidence (read-only). Editing the form does not change it.</p>
                    </div>
                    <div class="p-6">
                        @if(($snapshot['urgency'] ?? null) === 'URGENT_CLINICAL_REVIEW')
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 text-center">
                            URGENT CLINICAL REVIEW
                        </div>
                        @endif

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Visit Date</dt>
                                <dd class="mt-0.5 font-semibold text-gray-800">{{ ($snapshot['visit_date'] ?? null) ? \Carbon\Carbon::parse($snapshot['visit_date'])->format('M d, Y') : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Risk Level</dt>
                                <dd class="mt-0.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($snapshot['risk_level'] ?? null) === 'HIGH' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $snapshot['risk_level'] ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Assessment Date</dt>
                                <dd class="mt-0.5 text-gray-800">{{ ($snapshot['assessment_date'] ?? null) ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Decision Source</dt>
                                <dd class="mt-0.5 text-gray-800">
                                    @php
                                        $ds = $snapshot['decision_source'] ?? null;
                                        $dsLabel = match ($ds) {
                                            'RULE_BASED' => 'Rule-Based Clinical Assessment',
                                            'MACHINE_LEARNING' => 'Machine Learning Assessment',
                                            'COMPLETENESS' => 'Required Records Check',
                                            'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
                                            default => $ds ?: 'Legacy',
                                        };
                                    @endphp
                                    {{ $dsLabel }}
                                </dd>
                            </div>
                        </dl>

                        @if(!empty($snapshot['assessment']))
                        <div class="mt-4 rounded-xl bg-gray-50 border border-gray-100 p-3">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Clinical Assessment</p>
                            <p class="mt-1 text-sm text-gray-800">{{ $snapshot['assessment'] }}</p>
                        </div>
                        @endif

                        @if(!empty($snapshot['recommendation']))
                        <div class="mt-3 rounded-xl bg-gray-50 border-l-4 border-red-300 p-3">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Recommendation</p>
                            <p class="mt-1 text-sm text-gray-800">{{ $snapshot['recommendation'] }}</p>
                        </div>
                        @endif

                        @if(!empty($snapshot['factor_evidence']))
                        <div class="mt-4">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Clinical Factors</p>
                            <div class="mt-2 space-y-2">
                                @foreach($snapshot['factor_evidence'] as $factor)
                                @if(!is_array($factor)) @continue @endif
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-gray-100 bg-orange-50/40 px-3 py-2">
                                    <p class="text-sm text-gray-800">{{ $factor['label'] ?? $factor['code'] ?? 'Factor' }}</p>
                                    @if(!empty($factor['code']))
                                    <span class="shrink-0 text-xs font-mono text-gray-400">{{ $factor['code'] }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(!empty($snapshot['interaction_evidence']))
                        <div class="mt-4">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-violet-700">Clinical Interactions</p>
                            <div class="mt-2 space-y-2">
                                @foreach($snapshot['interaction_evidence'] as $interaction)
                                    @if(!is_array($interaction)) @continue @endif
                                    <div class="rounded-lg border border-violet-100 bg-violet-50 px-3 py-2">
                                        <p class="text-sm font-medium text-violet-900">{{ $interaction['label'] ?? $interaction['code'] ?? 'Interaction' }}</p>
                                        @php
                                            $contextLines = collect();
                                            foreach (($interaction['observed_context'] ?? []) as $path => $value) {
                                                if (isset($observedContextLabels[$path]) && $value !== null && trim((string) $value) !== '') {
                                                    $contextLines->push($observedContextLabels[$path] . ': ' . $value);
                                                }
                                            }
                                        @endphp
                                        @if($contextLines->isNotEmpty())
                                        <p class="mt-1 text-xs text-gray-600">{{ $contextLines->implode(' · ') }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(!empty($snapshot['bp_assessment']) && is_array($snapshot['bp_assessment']))
                        <div class="mt-4 rounded-lg border {{ ($snapshot['bp_assessment']['reason_code'] ?? null) === 'BP-URG' ? 'border-sky-200 bg-sky-50' : 'border-gray-100 bg-gray-50' }} p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Blood Pressure Finding</p>
                            @if(($snapshot['bp_assessment']['reason_code'] ?? null) === 'BP-URG')
                            <p class="mt-1 text-sm font-semibold text-red-700">Urgent blood-pressure finding captured in this assessment.</p>
                            @else
                            <p class="mt-1 text-sm text-gray-800">Blood-pressure finding captured in this assessment.</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Manual referral: single centered comfortable form --}}
        <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 bg-gray-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Referral Form</h2>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">Manual Referral</span>
            </div>
            <div class="p-6">
                <div class="mb-6 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-600 text-center">
                    Creating a manual referral for {{ $patient->first_name }} {{ $patient->last_name }}. No assessment is linked.
                </div>
                <form action="{{ route('referrals.store') }}" method="POST">
                    @csrf

                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Facility <span class="text-red-500">*</span></label>
                        <input type="text" name="referred_to" placeholder="e.g., Provincial Hospital, OB-GYN Clinic"
                            value="{{ old('referred_to') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Doctor (Optional)</label>
                        <input type="text" name="doctor_name" placeholder="Name of referring doctor"
                            value="{{ old('doctor_name') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason for Referral <span class="text-red-500">*</span></label>
                        <textarea name="reason" rows="4" placeholder="Example: Severe hypertension, breech presentation, high-risk pregnancy..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y">{{ old('reason') }}</textarea>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Any additional remarks..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y">{{ old('notes') }}</textarea>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Referral Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date_referred" value="{{ old('date_referred', \Carbon\Carbon::today()->toDateString()) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="flex gap-3">
                        <a href="{{ route('patients.show', $patient->id) }}"
                            class="flex-1 text-center px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-sm">
                            Back
                        </a>
                        <button type="submit"
                            class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold text-sm">
                            Save Referral
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>

</x-app-layout>