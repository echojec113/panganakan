@inject('fwaService', 'App\Services\FetalWeightAssessmentService')

@include('ultrasounds.partials.fetal-assessment-card-style')

@php
    $fwaOutcome = $fwaService->assess(
        $fwaWeight ?? null,
        $fwaGa ?? null
    );

    $fwaWeightValue = $fwaOutcome->rows['weight']['value'];
    $fwaGaValue = $fwaOutcome->rows['ga']['value'];

    if ($fwaWeightValue === 'Not recorded' && $fwaGaValue === 'Not recorded') {
        $fwaSummary = 'Enter fetal weight and gestational age to view the assessment.';
    } elseif ($fwaOutcome->reason !== '') {
        $fwaSummary = $fwaOutcome->reason;
    } else {
        $fwaSummary = $fwaWeightValue . ' at ' . $fwaGaValue . ' — ' . $fwaOutcome->label;
    }
@endphp

<details data-assessment="fwa" data-status="{{ $fwaOutcome->status }}">
    <summary class="fa-summary">
        <span class="fa-head">
            <span class="text-xs font-semibold uppercase tracking-wide text-green-800">Fetal Weight Assessment · Display Only</span>
            <span class="{{ $fwaOutcome->chipClass() }}" data-slot="chip">{{ $fwaOutcome->label }}</span>
        </span>

        <span class="fa-summary-text" data-slot="summary">{{ $fwaSummary }}</span>

        <span class="fa-toggle">
            <span class="fa-label fa-label-view">View Details</span>
            <span class="fa-label fa-label-hide">Hide Details</span>
            <svg class="fa-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 9l-7 7-7-7" /></svg>
        </span>
    </summary>

    <div class="fa-body">
        <dl class="fa-rows">
            @foreach ($fwaOutcome->rows as $rowKey => $row)
                <div class="flex flex-wrap gap-x-2" data-row="{{ $rowKey }}">
                    <dt class="font-medium text-gray-600 shrink-0">{{ $row['label'] }}</dt>
                    <dd class="text-gray-900" data-value>{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="text-gray-700 {{ $fwaOutcome->reason === '' ? 'hidden' : '' }}" data-slot="reason">{{ $fwaOutcome->reason }}</p>

        <p class="text-gray-700" data-slot="recommendation">{{ $fwaOutcome->recommendation }}</p>

        <p class="text-amber-800 {{ $fwaOutcome->clarification === null ? 'hidden' : '' }}" data-slot="clarification">{{ $fwaOutcome->clarification ?? '' }}</p>

        <p class="text-xs text-gray-500" data-slot="footnote">{{ $fwaOutcome->footnote }}</p>

        <p class="text-xs text-gray-500">
            Source:
            @foreach ($fwaOutcome->sources as $sourceIndex => $source)
                @if ($sourceIndex > 0)
                    ·
                @endif
                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">{{ $source['label'] }}</a>
            @endforeach
        </p>

        <p class="text-xs text-gray-400 italic" data-slot="pending">{{ $fwaOutcome->pendingNote }}</p>

        <button type="button" class="fa-hide-btn" data-hide-details
            onclick="var d = this.closest('details'); d.removeAttribute('open'); d.querySelector('summary').focus();">Hide Details</button>
    </div>
</details>
