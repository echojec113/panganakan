@inject('fhrService', 'App\Services\FetalHeartRateAssessmentService')

@include('ultrasounds.partials.fetal-assessment-card-style')

@php
    $fhrOutcome = $fhrService->assess(
        $fhrCategory ?? null,
        $fhrGa ?? null
    );

    $fhrCategoryValue = trim((string) ($fhrCategory ?? ''));
    $fhrGaValue = $fhrOutcome->rows['ga']['value'];

    if ($fhrCategoryValue === '') {
        $fhrSummary = 'Not recorded — select a fetal heartbeat finding to view the assessment.';
    } elseif ($fhrCategoryValue === 'Absent') {
        $fhrSummary = 'Absent — Prompt clinical assessment required. Confirm promptly with a qualified healthcare professional.';
    } elseif ($fhrOutcome->status === 'unable') {
        $fhrSummary = $fhrCategoryValue . ' — ' . $fhrOutcome->reason;
    } elseif ($fhrOutcome->status === 'within') {
        $fhrSummary = $fhrCategoryValue . ' at ' . $fhrGaValue . ' — within the applicable reference window.';
    } else {
        $fhrSummary = $fhrCategoryValue . ' at ' . $fhrGaValue . ' — needs clinical review with a qualified healthcare professional.';
    }
@endphp

<details data-assessment="fhr" data-status="{{ $fhrOutcome->status }}">
    <summary class="fa-summary">
        <span class="fa-head">
            <span class="text-xs font-semibold uppercase tracking-wide text-green-800">Fetal Heart Rate Assessment · Display Only</span>
            <span class="{{ $fhrOutcome->chipClass() }}" data-slot="chip">{{ $fhrOutcome->label }}</span>
        </span>

        <span class="fa-summary-text" data-slot="summary">{{ $fhrSummary }}</span>

        <span class="fa-toggle">
            <span class="fa-label fa-label-view">View Details</span>
            <span class="fa-label fa-label-hide">Hide Details</span>
            <svg class="fa-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 9l-7 7-7-7" /></svg>
        </span>
    </summary>

    <div class="fa-body">
        <dl class="fa-rows">
            @foreach ($fhrOutcome->rows as $rowKey => $row)
                <div class="flex flex-wrap gap-x-2" data-row="{{ $rowKey }}">
                    <dt class="font-medium text-gray-600 shrink-0">{{ $row['label'] }}</dt>
                    <dd class="text-gray-900" data-value>{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="text-gray-700 {{ $fhrOutcome->reason === '' ? 'hidden' : '' }}" data-slot="reason">{{ $fhrOutcome->reason }}</p>

        <p class="text-gray-700" data-slot="recommendation">{{ $fhrOutcome->recommendation }}</p>

        <p class="text-xs text-gray-600" data-slot="context">{{ $fhrOutcome->context }}</p>

        <p class="text-xs text-gray-600" data-slot="limitation">{{ $fhrOutcome->limitation }}</p>

        <p class="text-xs text-gray-500">
            Sources:
            @foreach ($fhrOutcome->sources as $sourceIndex => $source)
                @if ($sourceIndex > 0)
                    ·
                @endif
                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800 underline">{{ $source['label'] }}</a>
            @endforeach
        </p>

        <p class="text-xs text-gray-400 italic" data-slot="pending">{{ $fhrOutcome->pendingNote }}</p>

        <button type="button" class="fa-hide-btn" data-hide-details
            onclick="var d = this.closest('details'); d.removeAttribute('open'); d.querySelector('summary').focus();">Hide Details</button>
    </div>
</details>
