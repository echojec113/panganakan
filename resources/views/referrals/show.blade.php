<x-app-layout>

@php
    $linked = $referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0;
    $snapshotHas = is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0;
    $pregnancyStatus = $referral->patient?->status ?? 'Unknown';

    $pregnancyVariant = match ($pregnancyStatus) {
        'ONGOING' => 'info',
        'DELIVERED' => 'success',
        default => 'neutral',
    };

    $statusVariant = match ($referral->status) {
        'Pending' => 'warning',
        'Completed' => 'success',
        'Refused' => 'danger',
        default => 'neutral',
    };

    $snapshotRisk = is_array($referral->assessment_snapshot) ? ($referral->assessment_snapshot['risk_level'] ?? null) : null;
    $riskVariant = $snapshotRisk === 'HIGH'
        ? 'danger'
        : ($snapshotRisk === 'LOW' ? 'success' : 'neutral');

    $factorCategoryLabels = [
        'MATERNAL_DEMOGRAPHICS' => 'Maternal Demographics',
        'VITAL_SIGNS' => 'Vital Signs',
        'CURRENT_CONDITION' => 'Current Conditions',
        'OBSTETRIC_HISTORY' => 'Obstetric History',
        'ULTRASOUND' => 'Ultrasound Findings',
    ];
    $observedContextLabels = [
        'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
        'ultrasound_inputs.presentation' => 'Fetal presentation',
    ];
    $defensiveLabel = function ($v) { return ($v === null || trim((string) $v) === '') ? '—' : $v; };
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <x-flash type="success" :message="session('success')" class="mb-6" />
    <x-flash type="error" :message="session('error')" class="mb-6" />

    {{-- A. Patient + destination header --}}
    <x-app-header class="mb-8">
        <x-slot name="title">
            {{ $referral->patient->first_name }} {{ $referral->patient->middle_name ? $referral->patient->middle_name . ' ' : '' }}{{ $referral->patient->last_name }}
        </x-slot>
        <x-slot name="subtitle">
            {{ $referral->referred_to }}
            @if($referral->doctor_name)
                <span class="text-gray-400">·</span> Dr. {{ $referral->doctor_name }}
            @endif
            <span class="text-gray-400">·</span> Referral date {{ $referral->referral_date?->format('M d, Y') }}
        </x-slot>
        <x-slot name="actions">
            <a href="{{ route('referrals.index') }}" class="btn btn-secondary">Back to Referrals</a>
            <a href="{{ route('referrals.print', $referral->id) }}" class="btn btn-primary">Print Referral Letter</a>
        </x-slot>
    </x-app-header>

    {{-- B. Pregnancy / Referral / Risk summary (kept conceptually separate) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pregnancy</p>
            <x-status-badge :variant="$pregnancyVariant" class="mt-2">
                {{ $pregnancyStatus }}
            </x-status-badge>
        </div>
        <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Referral</p>
            <x-status-badge :variant="$statusVariant" class="mt-2">
                {{ $referral->status }}
            </x-status-badge>
        </div>
        <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Clinical Risk</p>
            @if($snapshotHas)
                <x-status-badge :variant="$riskVariant" class="mt-2">
                    {{ $defensiveLabel($snapshotRisk) }}
                </x-status-badge>
            @else
                <p class="mt-2 text-sm text-gray-500">Not assessed</p>
            @endif
        </div>
    </div>

    {{-- C + E. Referral Information + Follow-up --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Referral Information --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Referral Information</h2>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Referral Date</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $referral->referral_date?->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Destination</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $referral->referred_to }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Doctor</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $referral->doctor_name ? 'Dr. ' . $referral->doctor_name : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Source</dt>
                        <dd class="mt-1">
                            @if($linked)
                                <x-status-badge variant="info">Assessment-linked</x-status-badge>
                            @else
                                <x-status-badge variant="neutral">Manual Referral</x-status-badge>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Reason for Referral</p>
                    <p class="mt-2 text-sm text-gray-800 leading-relaxed">{{ $referral->reason }}</p>
                </div>

                @if($referral->notes)
                <div class="mt-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Additional Notes</p>
                    <p class="mt-2 text-sm text-gray-800 leading-relaxed">{{ $referral->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Follow-through --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Referral Follow-through</h2>
            </div>
            <div class="p-6">
                @if($referral->status === 'Pending')
                    <p class="text-sm text-gray-600">This referral is awaiting clinic follow-through.</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <form method="POST" action="{{ route('referrals.complete', $referral->id) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Mark this referral as completed?')"
                                class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition">
                                Mark Completed
                            </button>
                        </form>
                        <button type="button" onclick="openRefuseModal()"
                            class="w-full px-4 py-2.5 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition">
                            Record Refusal
                        </button>
                        <form method="POST" action="{{ route('referrals.cancel', $referral->id) }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Cancel this pending referral? This cannot be undone.')"
                                class="w-full px-4 py-2.5 bg-gray-700 text-white rounded-lg text-sm font-semibold hover:bg-gray-800 transition">
                                Cancel Referral
                            </button>
                        </form>
                    </div>
                @elseif($referral->status === 'Completed')
                    <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                        <p class="text-sm font-medium text-gray-800">Clinic staff recorded the referral follow-through as completed.</p>
                        <dl class="mt-3 text-sm">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Completed On</dt>
                                <dd class="mt-0.5 font-semibold text-green-700">{{ $referral->completed_at?->format('M d, Y g:i A') }}</dd>
                            </div>
                        </dl>
                    </div>
                @elseif($referral->status === 'Refused')
                    <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                        <p class="text-sm font-medium text-gray-800">Referral Refusal Record</p>
                        <p class="mt-2 text-sm text-gray-600">`completed_at` is not set because the referral was refused rather than completed.</p>
                        <dl class="mt-3 text-sm space-y-2">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Recorded On</dt>
                                <dd class="mt-0.5 font-semibold text-gray-800">{{ $referral->refusal_recorded_at?->format('M d, Y g:i A') ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Recorded By</dt>
                                <dd class="mt-0.5 font-semibold text-gray-800">{{ $referral->refusalRecordedBy?->name ?? 'Staff account no longer available' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Physical waiver signed/recorded</dt>
                                <dd class="mt-0.5 font-semibold text-gray-800">{{ $referral->waiver_signed ? 'Yes' : 'No' }}</dd>
                            </div>
                        </dl>
                        @if($referral->refusal_notes)
                        <div class="mt-3 pt-3 border-t border-orange-100">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Refusal Notes</p>
                            <p class="mt-1 text-sm text-gray-800">{{ $referral->refusal_notes }}</p>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-sm font-medium text-gray-800">Cancelled Referral</p>
                        <p class="mt-2 text-sm text-gray-600">This referral was closed as cancelled.</p>
                        <p class="mt-1 text-xs text-gray-400">The original creation notes are unchanged and no cancellation reason is recorded.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- D. Assessment Evidence at Referral (linked, immutable snapshot) --}}
    @if(is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
    @php
        $snap = $referral->assessment_snapshot;
        $factorEvidence = array_values(array_filter(
            is_array($snap['factor_evidence'] ?? null) ? $snap['factor_evidence'] : [],
            'is_array'
        ));
        $interactionEvidence = array_values(array_filter(
            is_array($snap['interaction_evidence'] ?? null) ? $snap['interaction_evidence'] : [],
            'is_array'
        ));
        $bpAssessment = is_array($snap['bp_assessment'] ?? null) ? $snap['bp_assessment'] : [];
        $versions = is_array($snap['versions'] ?? null) ? $snap['versions'] : [];
    @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h2 class="text-lg font-semibold text-gray-800">Assessment Evidence at Referral</h2>
                <span class="text-xs text-gray-500">This is the assessment evidence preserved when this referral was created.</span>
            </div>
        </div>

        <div class="p-6">
            @if(($snap['urgency'] ?? null) === 'URGENT_CLINICAL_REVIEW')
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 text-center">
                URGENT CLINICAL REVIEW
            </div>
            @endif

            {{-- Fingerprint grid --}}
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Assessment Date</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-gray-800">{{ $defensiveLabel($snap['assessment_date'] ?? null) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Visit Date</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-gray-800">{{ ($snap['visit_date'] ?? null) ? \Carbon\Carbon::parse($snap['visit_date'])->format('M d, Y') : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Risk Level</dt>
                    <dd class="mt-0.5">
                        <x-status-badge :variant="$riskVariant">
                            {{ $defensiveLabel($snap['risk_level'] ?? null) }}
                        </x-status-badge>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Decision Source</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-gray-800">
                        @php
                            $ds = $snap['decision_source'] ?? null;
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

            {{-- Summary + recommendation --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Clinical Assessment</p>
                    <p class="mt-2 text-sm text-gray-800">{{ $snap['assessment'] ?? 'No assessment text recorded.' }}</p>
                </div>
                <div class="rounded-xl border-l-4 border-red-400 bg-gray-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Recommendation</p>
                    <p class="mt-2 text-sm text-gray-800">{{ $snap['recommendation'] ?? 'No recommendation recorded.' }}</p>
                </div>
            </div>

            {{-- Blood pressure finding --}}
            @if(!empty($bpAssessment))
            <div class="mb-6 rounded-xl border p-4 {{ ($bpAssessment['reason_code'] ?? null) === 'BP-URG' ? 'border-sky-200 bg-sky-50' : 'border-gray-200 bg-gray-50' }}">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Blood Pressure Finding</p>
                @if(($bpAssessment['reason_code'] ?? null) === 'BP-URG')
                <p class="mt-2 text-sm font-semibold text-red-700">Urgent blood-pressure finding captured in this assessment.</p>
                @elseif(!empty($bpAssessment['label']))
                <p class="mt-2 text-sm text-gray-800">{{ $bpAssessment['label'] }}</p>
                @else
                <p class="mt-2 text-sm text-gray-800">Blood-pressure finding captured in this assessment.</p>
                @endif
            </div>
            @endif

            {{-- Clinical factors: readable rows (label primary, code secondary) --}}
            @if(!empty($factorEvidence))
            <div class="mb-6">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-3">Clinical Factors</p>
                @php
                    $groupedFactors = [];
                    foreach ($factorEvidence as $factor) {
                        $cat = $factor['category'] ?? 'OTHER';
                        $groupedFactors[$cat][] = $factor;
                    }
                @endphp
                @foreach($factorCategoryLabels as $cat => $catLabel)
                    @if(!empty($groupedFactors[$cat]))
                    <div class="mb-4">
                        <p class="text-xs font-medium text-gray-700 mb-2">{{ $catLabel }}</p>
                        <div class="space-y-2">
                            @foreach($groupedFactors[$cat] as $factor)
                            <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-100 bg-slate-50/50 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">{{ $factor['label'] ?? $factor['code'] ?? 'Factor' }}</p>
                                    @php
                                        $observed = $factor['observed_value'] ?? null;
                                    @endphp
                                    @if ($observed !== null && trim((string) $observed) !== '')
                                    <p class="text-xs text-gray-500 mt-0.5">Observed: {{ \App\ValueObjects\ClinicalFactorEvidence::displayObserved($observed) }}</p>
                                    @endif
                                </div>
                                @if(!empty($factor['code']))
                                <span class="shrink-0 text-xs font-mono text-gray-400">{{ $factor['code'] }}</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            {{-- Clinical interactions --}}
            @if(!empty($interactionEvidence))
            <div class="mb-6 rounded-xl border border-violet-100 bg-violet-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Clinical Interactions Detected</p>
                <p class="mt-1 text-xs text-violet-600">Additional relationships between the clinical findings above. They support review and planning and do not change the final risk classification.</p>
                <div class="mt-4 space-y-3">
                    @foreach($interactionEvidence as $interaction)
                    @php
                        $contextLines = collect();
                        foreach (($interaction['observed_context'] ?? []) as $path => $value) {
                            if (isset($observedContextLabels[$path]) && $value !== null && trim((string) $value) !== '') {
                                $contextLines->push($observedContextLabels[$path] . ': ' . $value);
                            }
                        }
                    @endphp
                    <div class="rounded-xl border border-violet-100 bg-white p-4">
                        <div class="flex items-start justify-between gap-4">
                            <p class="text-sm font-semibold text-violet-900">{{ $interaction['label'] ?? 'Clinical interaction' }}</p>
                            @if(!empty($interaction['code']))
                            <span class="shrink-0 text-xs font-mono text-violet-500">{{ $interaction['code'] }}</span>
                            @endif
                        </div>

                        @if(!empty($interaction['required_factor_codes']))
                        <div class="mt-2">
                            <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wide">Contributing factors</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach($interaction['required_factor_codes'] as $code)
                                <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-mono text-violet-700">{{ $code }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if($contextLines->isNotEmpty())
                        <div class="mt-2">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Evaluated finding</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach($contextLines as $line)
                                <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs text-gray-600">{{ $line }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(!empty($interaction['explanation']))
                        <p class="mt-2 text-sm text-gray-600">{{ $interaction['explanation'] }}</p>
                        @endif

                        @if(!empty($interaction['suggested_action']))
                        <p class="mt-2 text-sm font-medium text-violet-700">Suggested clinical follow-through: {{ $interaction['suggested_action'] }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- F. Historical / version metadata (low priority) --}}
            @if(count($versions) > 0 || ($snap['schema_version'] ?? null) !== null)
            <div class="border-t border-gray-100 pt-4 text-[11px] text-gray-400">
                @if(!empty($versions['clinical_rules']))
                    Clinical Rules Version: {{ $versions['clinical_rules'] }}
                @endif
                @if(!empty($versions['assessment_engine']))
                    Assessment Engine Version: {{ $versions['assessment_engine'] }}
                @endif
                @if(($snap['schema_version'] ?? null) !== null && empty($versions['clinical_rules']) && empty($versions['assessment_engine']))
                    Snapshot Schema Version: {{ $snap['schema_version'] }}
                @endif
            </div>
            @endif
        </div>
    </div>
    @else
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-800">Manual Referral</h2>
        </div>
        <div class="p-6">
            <p class="text-sm text-gray-600">No linked prenatal assessment record. This referral was created manually. This evidence is not available for a manual referral.</p>
        </div>
    </div>
    @endif

</div>

{{-- Refusal Modal (Pending referrals only) --}}
@if($referral->status === 'Pending')
<div id="refuseModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Record Referral Refusal</h3>
            <button type="button" onclick="closeRefuseModal()" aria-label="Close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" id="refuseForm">
            @csrf
            <p class="text-sm text-gray-500">
                Record that the patient refused this referral. The staff member and time are recorded automatically.
            </p>
            <label for="refusal-notes" class="block mt-4 text-sm font-medium text-gray-700">Refusal Notes <span class="text-red-500">*</span></label>
            <textarea id="refusal-notes" name="refusal_notes" rows="3" required minlength="10" maxlength="2000"
                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 resize-y"
                placeholder="Document the patient's refusal (required)."></textarea>
            <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="waiver_signed" value="1" class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                Physical waiver signed/recorded
            </label>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRefuseModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">Back</button>
                <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-semibold hover:bg-amber-600 transition">Record Refusal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRefuseModal() {
        document.getElementById('refuseForm').action = "{{ url('referrals') }}" + '/' + {{ $referral->id }} + '/refuse';
        document.getElementById('refuseModal').style.display = 'flex';
    }
    function closeRefuseModal() {
        document.getElementById('refuseModal').style.display = 'none';
    }
</script>
@endif

</x-app-layout>