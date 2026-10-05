<x-app-layout>

@php
    $hasPendingAny = $pending > 0;
    $sourceLabel = function ($referral) {
        return ($referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
            ? 'Assessment-linked'
            : 'Manual Referral';
    };
    $sourceVariant = function ($referral) {
        return ($referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
            ? 'info'
            : 'neutral';
    };
    $statusVariant = function ($status) {
        return match ($status) {
            'Pending'   => 'warning',
            'Completed' => 'success',
            'Refused'   => 'danger',
            default     => 'neutral',
        };
    };
@endphp

<div class="min-h-screen bg-[#FCFBF8]">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Header --}}
    <x-app-header
        title="Referral Management"
        subtitle="Track referral decisions and clinical follow-through."
        class="mb-8"
    >
        @if(auth()->user()->role === 'staff')
            <x-slot name="actions">
                <a href="{{ route('referrals.archived') }}" class="btn btn-secondary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8m-9 4v4m4-4v4" />
                    </svg>
                    Archived
                </a>
                <a href="{{ route('referrals.select-patient') }}" class="btn bg-[#55B85A] text-white transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Referral
                </a>
            </x-slot>
        @endif
    </x-app-header>

    <x-flash type="success" :message="session('success')" class="mb-6" />
    <x-flash type="error" :message="session('error')" class="mb-6" />

    {{-- Referral Status Summary --}}
    <div class="mb-8 rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="grid grid-cols-2 lg:grid-cols-4">
            <div class="p-6 text-center border-r border-b lg:border-b-0 border-gray-100">
                <p class="text-2xl font-bold leading-none text-amber-700">{{ $pending }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Pending</p>
                @if($hasPendingAny)
                    <p class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Action Required</p>
                @endif
            </div>
            <div class="p-6 text-center border-r border-b lg:border-b-0 border-gray-100">
                <p class="text-2xl font-bold leading-none text-green-600">{{ $completed }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Completed</p>
            </div>
            <div class="p-6 text-center border-r border-gray-100">
                <p class="text-2xl font-bold leading-none text-orange-600">{{ $refused }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Refused</p>
            </div>
            <div class="p-6 text-center">
                <p class="text-2xl font-bold leading-none text-gray-500">{{ $cancelled }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Cancelled</p>
            </div>
        </div>
    </div>

    {{-- Operational Referral Management --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">

        {{-- Search & Filter Bar --}}
        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <form method="GET" action="{{ route('referrals.index') }}">
                    <input type="text" name="search" placeholder="Search by patient name..."
                        value="{{ request('search') }}"
                        class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                </form>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <form method="GET" action="{{ route('referrals.index') }}" class="w-full sm:w-auto">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full sm:w-auto px-4 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                        <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                        <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                        <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                        <option value="Refused" {{ request('status') === 'Refused' ? 'selected' : '' }}>Refused</option>
                        <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </form>
                @if(request('search') || request('status'))
                    <a href="{{ route('referrals.index') }}"
                        class="px-3 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition whitespace-nowrap">
                        Clear
                    </a>
                @endif
            </div>
        </div>

        {{-- Mobile Cards --}}
        <div class="lg:hidden divide-y divide-gray-100">
            @forelse($referrals as $patient)
                @php($latestReferral = $patient->latestReferral)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('patients.show', $patient->id) }}" class="text-sm font-semibold text-gray-800 hover:text-blue-600">
                                {{ $patient->first_name }} {{ $patient->last_name }}
                            </a>
                            <p class="text-xs text-gray-500 truncate mt-0.5">
                                {{ $latestReferral->referred_to }}
                                <span class="text-gray-400">·</span>
                                {{ $latestReferral->referral_date->format('M d, Y') }}
                            </p>
                            @if($latestReferral->prenatal_visit_id)
                                <p class="text-[11px] text-gray-400 mt-0.5">Visit: {{ $latestReferral->prenatalVisit?->visit_date?->format('M d, Y') ?? $latestReferral->referral_date->format('M d, Y') }}</p>
                            @endif
                        </div>
                        <x-status-badge :variant="$statusVariant($latestReferral->status)" class="shrink-0">
                            {{ $latestReferral->status }}
                        </x-status-badge>
                    </div>

                    <p class="mt-2 text-xs text-gray-600 line-clamp-2">{{ \Str::limit($latestReferral->reason, 50) }}</p>

                    <div class="mt-2 flex items-center gap-2">
                        <x-status-badge :variant="$sourceVariant($latestReferral)">
                            {{ $sourceLabel($latestReferral) }}
                        </x-status-badge>
                        @if($latestReferral->status === 'Refused' && $latestReferral->refusal_recorded_at)
                            <span class="text-[11px] text-gray-400">Recorded {{ $latestReferral->refusal_recorded_at->format('M d') }}</span>
                        @elseif($latestReferral->status === 'Completed' && $latestReferral->completed_at)
                            <span class="text-[11px] text-gray-400">Completed {{ $latestReferral->completed_at->format('M d, Y') }}</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('referrals.show', $latestReferral->id) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#55B85A] text-white text-xs font-semibold rounded-lg hover:bg-[#4aa04c] transition shadow-sm">
                            View Referral
                        </a>
                        <button type="button" onclick="toggleReferralHistory({{ $patient->id }})"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition"
                            aria-expanded="false" aria-controls="referral-history-mobile-{{ $patient->id }}">
                            History ({{ $patient->referrals->count() }})
                        </button>
                        @if(auth()->user()->role === 'staff')
                            <form action="{{ route('referrals.destroy', $latestReferral->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="confirmArchiveReferral(this)"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-700 hover:bg-red-50 hover:text-red-900 transition-all duration-150"
                                    title="Archive" aria-label="Archive referral">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>

                    <div id="referral-history-mobile-{{ $patient->id }}" class="hidden mt-3 border-t border-gray-100 pt-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Referral History ({{ $patient->referrals->count() }})</p>
                        @include('referrals.partials.history-list', [
                            'patient' => $patient,
                            'statusVariant' => $statusVariant,
                            'sourceVariant' => $sourceVariant,
                            'sourceLabel' => $sourceLabel,
                        ])
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">No referrals found.</div>
            @endforelse
        </div>

        {{-- Desktop Table --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($referrals as $patient)
                        @php($latestReferral = $patient->latestReferral)
                        <tr class="hover:bg-gray-50 transition cursor-pointer" onclick="toggleReferralHistory({{ $patient->id }})">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <a href="{{ route('patients.show', $patient->id) }}" class="font-medium hover:text-blue-600">
                                    {{ $patient->first_name }} {{ $patient->last_name }}
                                </a>
                                @if($latestReferral->prenatal_visit_id)
                                    <div class="text-[11px] text-gray-400">Visit: {{ $latestReferral->prenatalVisit?->visit_date?->format('M d, Y') ?? $latestReferral->referral_date->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $latestReferral->referred_to }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $latestReferral->referral_date->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ \Str::limit($latestReferral->reason, 50) }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :variant="$sourceVariant($latestReferral)">
                                    {{ $sourceLabel($latestReferral) }}
                                </x-status-badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :variant="$statusVariant($latestReferral->status)">
                                    {{ $latestReferral->status }}
                                </x-status-badge>
                                @if($latestReferral->status === 'Refused' && $latestReferral->refusal_recorded_at)
                                    <div class="text-[11px] text-gray-400 mt-0.5">Recorded {{ $latestReferral->refusal_recorded_at->format('M d') }}</div>
                                @elseif($latestReferral->status === 'Completed' && $latestReferral->completed_at)
                                    <div class="text-[11px] text-gray-400 mt-0.5">{{ $latestReferral->completed_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('referrals.show', $latestReferral->id) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#55B85A] text-white text-xs font-semibold rounded-lg hover:bg-[#4aa04c] transition shadow-sm">
                                        View Referral
                                    </a>
                                    <button type="button" onclick="toggleReferralHistory({{ $patient->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition"
                                        aria-expanded="false" aria-controls="referral-history-{{ $patient->id }}">
                                        History ({{ $patient->referrals->count() }})
                                    </button>
                                    @if(auth()->user()->role === 'staff')
                                        <form action="{{ route('referrals.destroy', $latestReferral->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="confirmArchiveReferral(this)"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-700 hover:bg-red-50 hover:text-red-900 transition-all duration-150"
                                                title="Archive" aria-label="Archive referral">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr id="referral-history-{{ $patient->id }}" class="hidden bg-gray-50">
                            <td colspan="7" class="px-4 py-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Referral History ({{ $patient->referrals->count() }})</p>
                                @include('referrals.partials.history-list', [
                                    'patient' => $patient,
                                    'statusVariant' => $statusVariant,
                                    'sourceVariant' => $sourceVariant,
                                    'sourceLabel' => $sourceLabel,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">No referrals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($referrals->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $referrals->links() }}
            </div>
        @endif
    </div>

    {{-- Referral Analytics --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">

    {{-- Analytics Header --}}
<div class="px-6 py-5 border-b border-gray-100
            flex flex-col gap-4
            sm:flex-row sm:items-end sm:justify-between">

    {{-- Title --}}
    <div>
        <p class="text-sm font-semibold text-gray-800">
            Referral Analytics
        </p>

        <p
            id="referralAnalyticsSubtitle"
            class="mt-0.5 text-xs text-gray-500"
        >
            @if($analytics['month'] ?? null)
                Showing referral analytics for
                {{ \Carbon\Carbon::create($analytics['year'], $analytics['month'], 1)->format('F Y') }}
            @else
                Showing referral analytics for {{ $analytics['year'] ?? now()->year }}
            @endif
        </p>
    </div>

    {{-- Analytics Filters --}}
    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">

        {{-- Year Filter --}}
        <div class="w-full sm:w-auto">
            <label
                for="referralAnalyticsYear"
                class="mb-1 block text-xs font-medium text-gray-500"
            >
                Year
            </label>

            <select
                id="referralAnalyticsYear"
                class="block w-full min-w-[110px]
                       rounded-lg border border-gray-300 bg-white
                       px-3 py-2 text-sm text-gray-700
                       focus:border-blue-500
                       focus:ring-1 focus:ring-blue-500
                       sm:w-auto"
            >
                @foreach(($analytics['availableYears'] ?? [now()->year]) as $year)
                    <option
                        value="{{ $year }}"
                        {{ (int) ($analytics['year'] ?? now()->year) === (int) $year ? 'selected' : '' }}
                    >
                        {{ $year }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Month Filter --}}
        <div class="w-full sm:w-auto">
            <label
                for="referralAnalyticsMonth"
                class="mb-1 block text-xs font-medium text-gray-500"
            >
                Month
            </label>

            <select
                id="referralAnalyticsMonth"
                class="block w-full min-w-[140px]
                       rounded-lg border border-gray-300 bg-white
                       px-3 py-2 text-sm text-gray-700
                       focus:border-blue-500
                       focus:ring-1 focus:ring-blue-500
                       sm:w-auto"
            >
                <option value="">All Months</option>

                @for($m = 1; $m <= 12; $m++)
                    <option
                        value="{{ $m }}"
                        {{ ($analytics['month'] ?? null) === $m ? 'selected' : '' }}
                    >
                        {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                    </option>
                @endfor
            </select>
        </div>

        {{-- Loading Indicator --}}
        <div class="sm:pb-2">
            <span
                id="referralAnalyticsLoading"
                class="block text-xs text-gray-500"
                style="display: none;"
            >
                Loading&hellip;
            </span>
        </div>

    </div>
</div>

{{-- Referral Analytics Charts --}}

{{-- Analytics Content --}}
<div class="p-6">

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total Referrals --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                Total Referrals
            </p>

            <p
                id="referralSummaryTotal"
                class="mt-2 text-2xl font-bold text-gray-900"
            >
                {{ $analytics['summary']['totalReferrals'] ?? 0 }}
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Within selected period
            </p>
        </div>


        {{-- Completed Referrals --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                Completed Referrals
            </p>

            <p
                id="referralSummaryCompleted"
                class="mt-2 text-2xl font-bold text-green-600"
            >
                {{ $analytics['summary']['completedReferrals'] ?? 0 }}
            </p>

            <p class="mt-1 text-xs text-gray-500">
                Within selected period
            </p>
        </div>


        {{-- Most Referred Facility --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                Most Referred Facility
            </p>

            <p
                id="referralSummaryFacility"
                class="mt-2 truncate text-base font-bold text-gray-900"
                title="{{ $analytics['summary']['mostReferredFacility']['label'] ?? '' }}"
            >
                {{ $analytics['summary']['mostReferredFacility']['label'] ?? '—' }}
            </p>

            <p
                id="referralSummaryFacilitySub"
                class="mt-1 text-xs text-gray-500"
            >
                @if($analytics['summary']['mostReferredFacility'] ?? null)
                    {{ $analytics['summary']['mostReferredFacility']['count'] }}
                    {{ $analytics['summary']['mostReferredFacility']['count'] === 1 ? 'referral' : 'referrals' }}
                @else
                    No referral data
                @endif
            </p>
        </div>


        {{-- Busiest Period --}}
        <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4">
            <p
                id="referralSummaryBusiestTitle"
                class="text-xs font-medium uppercase tracking-wide text-gray-500"
            >
                {{ ($analytics['month'] ?? null) ? 'Busiest Day' : 'Busiest Month' }}
            </p>

            <p
                id="referralSummaryBusiest"
                class="mt-2 text-base font-bold text-gray-900"
            >
                {{ $analytics['summary']['busiestPeriod']['label'] ?? '—' }}
            </p>

            <p
                id="referralSummaryBusiestSub"
                class="mt-1 text-xs text-gray-500"
            >
                @if($analytics['summary']['busiestPeriod'] ?? null)
                    {{ $analytics['summary']['busiestPeriod']['count'] }}
                    {{ $analytics['summary']['busiestPeriod']['count'] === 1 ? 'referral' : 'referrals' }}
                @else
                    No referral data
                @endif
            </p>
        </div>

    </div>



        {{-- Referral Trend --}}
    <div class="mt-6 rounded-xl border border-gray-100 bg-white p-5">
        <div class="mb-5">
            <p class="text-sm font-semibold text-gray-800">
                Referral Trend
            </p>

            <p class="mt-0.5 text-xs text-gray-500">
                Referral volume across the selected period.
            </p>
        </div>

        <div class="relative h-[300px]">
            <canvas id="referralTrendChart"></canvas>

            <div
                id="referralTrendEmpty"
                class="absolute inset-0 flex items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 text-center text-sm text-gray-400"
                style="display: none;"
            >
                No referral data for the selected period.
            </div>
        </div>
    </div>

    {{-- Referral Status --}}
    <div class="mt-6 rounded-xl border border-gray-100 bg-white p-5">
        <div class="mb-5">
            <p class="text-sm font-semibold text-gray-800">
                Referral Status
            </p>

            <p class="mt-0.5 text-xs text-gray-500">
                Distribution of referral outcomes within the selected period.
            </p>
        </div>

        <div class="relative h-[300px]">
            <canvas id="referralStatusChart"></canvas>

            <div
                id="referralDestinationsEmpty"
                class="absolute inset-0 flex items-center justify-center text-sm text-gray-400"
                style="display: none;"
            >
                No referral data for the selected period.
            </div>
        </div>
    </div>
    </div>
        {{-- Top Referral Destinations --}}
    <div class="mt-6 rounded-xl border border-gray-100 bg-white p-5">
        <div class="mb-5">
            <p class="text-sm font-semibold text-gray-800">
                Top Referral Destinations
            </p>

            <p class="mt-0.5 text-xs text-gray-500">
                Facilities receiving the most referrals within the selected period.
            </p>
        </div>

        <div class="relative h-[320px]">
            <canvas id="referralDestinationsChart"></canvas>

            <div
                id="referralDestinationsEmpty"
                class="absolute inset-0 flex items-center justify-center text-sm text-gray-400"
                style="display: none;"
            >
                No referral data for the selected period.
            </div>
        </div>
    </div>

</div> {{-- closes Analytics Content --}}

</div> {{-- closes Referral Analytics --}}




<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialAnalytics = {!! json_encode($analytics ?? []) !!};

    const palette = {
        blue: '#55B85A', emerald: '#367E4B', amber: '#d97706',
        violet: '#7c3aed', red: '#dc2626', slate: '#64748b',
        gridLine: 'rgba(0,0,0,0.04)',
    };

    const baseFont = { family: "'DM Sans', sans-serif", size: 12 };

    const sharedTooltip = {
        backgroundColor: '#0f172a',
        titleFont: { ...baseFont, size: 12, weight: '600' },
        bodyFont: { ...baseFont, size: 12 },
        padding: 10,
        cornerRadius: 8,
        displayColors: true,
        boxWidth: 10, boxHeight: 10, boxPadding: 4,
    };

    const charts = {};

    function destroyChart(id) {
        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }
    }

    function makeChart(id, config) {
        destroyChart(id);
        const canvas = document.getElementById(id);
        if (!canvas) return;
        charts[id] = new Chart(canvas.getContext('2d'), config);
    }

    function toggleEmpty(canvasId, emptyId, hasData) {
        const canvas = document.getElementById(canvasId);
        const empty = document.getElementById(emptyId);
        if (canvas) canvas.style.display = hasData ? 'block' : 'none';
        if (empty) empty.style.display = hasData ? 'none' : 'block';
    }

    function setText(id, value) {
        const el = document.getElementById(id);

        if (!el) return;

        el.textContent =
            value === null || value === undefined || value === ''
                ? '—'
                : value;
    }
    function renderAnalytics(analytics) {
    const summary = analytics.summary || {};
    const trend = analytics.trend || {
        granularity: 'month',
        labels: [],
        data: [],
    };

    const status = analytics.status || {
        pending: 0,
        completed: 0,
        refused: 0,
        cancelled: 0,
    };

    const isSingleMonth = analytics.month !== null
        && analytics.month !== undefined
        && analytics.month !== '';

    /*
    |--------------------------------------------------------------------------
    | Analytics Subtitle
    |--------------------------------------------------------------------------
    */

    const subtitleEl = document.getElementById(
        'referralAnalyticsSubtitle'
    );

    if (subtitleEl) {
        if (isSingleMonth) {
            const monthName = new Intl.DateTimeFormat('en', {
                month: 'long',
            }).format(
                new Date(
                    Number(analytics.year),
                    Number(analytics.month) - 1,
                    1
                )
            );

            subtitleEl.textContent =
                'Showing referral analytics for ' +
                monthName +
                ' ' +
                analytics.year;
        } else {
            subtitleEl.textContent =
                'Showing referral analytics for ' +
                analytics.year;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Summary Cards
    |--------------------------------------------------------------------------
    */

    setText(
        'referralSummaryTotal',
        summary.totalReferrals ?? 0
    );

    setText(
        'referralSummaryCompleted',
        summary.completedReferrals ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Most Referred Facility
    |--------------------------------------------------------------------------
    */

    const facility = summary.mostReferredFacility || null;

    setText(
        'referralSummaryFacility',
        facility ? facility.label : null
    );

    const facilityEl = document.getElementById(
        'referralSummaryFacility'
    );

    if (facilityEl) {
        facilityEl.title = facility ? facility.label : '';
    }

    const facilitySubEl = document.getElementById(
        'referralSummaryFacilitySub'
    );

    if (facilitySubEl) {
        if (facility) {
            facilitySubEl.textContent =
                facility.count +
                (facility.count === 1
                    ? ' referral'
                    : ' referrals');
        } else {
            facilitySubEl.textContent = 'No referral data';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Busiest Month / Day
    |--------------------------------------------------------------------------
    */

    const busiest = summary.busiestPeriod || null;

    const busiestTitleEl = document.getElementById(
        'referralSummaryBusiestTitle'
    );

    if (busiestTitleEl) {
        busiestTitleEl.textContent =
            isSingleMonth
                ? 'Busiest Day'
                : 'Busiest Month';
    }

    setText(
        'referralSummaryBusiest',
        busiest ? busiest.label : null
    );

    const busiestSubEl = document.getElementById(
        'referralSummaryBusiestSub'
    );

    if (busiestSubEl) {
        if (busiest) {
            busiestSubEl.textContent =
                busiest.count +
                (busiest.count === 1
                    ? ' referral'
                    : ' referrals');
        } else {
            busiestSubEl.textContent = 'No referral data';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Empty-State Messages
    |--------------------------------------------------------------------------
    */

    const noDataMessage = isSingleMonth
        ? 'No referral data for the selected month.'
        : 'No referral data for the selected year.';

    [
        'referralTrendEmpty',
        'referralStatusEmpty',
        'referralDestinationsEmpty',
    ].forEach((id) => {
        const el = document.getElementById(id);

        if (el) {
            el.textContent = noDataMessage;
        }
    });


    /*
    |--------------------------------------------------------------------------
    | Referral Trend
    |--------------------------------------------------------------------------
    |
    | All Months:
    | Jan -> Dec
    |
    | Specific Month:
    | Sep 1 -> Sep 30
    |
    */

    const trendLabels = Array.isArray(trend.labels)
        ? trend.labels
        : [];

    const trendData = Array.isArray(trend.data)
        ? trend.data
        : [];

    const trendTotal = trendData.reduce(
        (total, value) => total + Number(value || 0),
        0
    );

    const hasTrend = trendTotal > 0;

    toggleEmpty(
        'referralTrendChart',
        'referralTrendEmpty',
        hasTrend
    );

    if (hasTrend) {
        makeChart('referralTrendChart', {
            type: 'line',

            data: {
                labels: trendLabels,

                datasets: [{
                    label: 'Referrals',
                    data: trendData,

                    borderColor: palette.blue,

                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const ctx = chart.ctx;
                        const chartArea = chart.chartArea;

                        if (!chartArea) {
                            return 'rgba(85,184,90,0.10)';
                        }

                        const gradient = ctx.createLinearGradient(
                            0,
                            chartArea.top,
                            0,
                            chartArea.bottom
                        );

                        gradient.addColorStop(
                            0,
                            'rgba(85,184,90,0.18)'
                        );

                        gradient.addColorStop(
                            1,
                            'rgba(85,184,90,0)'
                        );

                        return gradient;
                    },

                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,

                    pointBackgroundColor: palette.blue,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,

                    pointRadius:
                        trend.granularity === 'day'
                            ? 3
                            : 4,

                    pointHoverRadius: 6,
                }],
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index',
                },

                plugins: {
                    legend: {
                        display: false,
                    },

                    tooltip: sharedTooltip,
                },

                scales: {
                    y: {
                        beginAtZero: true,

                        grid: {
                            color: palette.gridLine,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#94a3b8',
                            precision: 0,
                            maxTicksLimit: 5,
                        },

                        border: {
                            display: false,
                        },
                    },

                    x: {
                        grid: {
                            display: false,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#94a3b8',

                            autoSkip:
                                trend.granularity === 'day',

                            maxTicksLimit:
                                trend.granularity === 'day'
                                    ? 10
                                    : 12,

                            maxRotation: 0,
                            minRotation: 0,
                        },

                        border: {
                            display: false,
                        },
                    },
                },
            },
        });
    } else {
        destroyChart('referralTrendChart');
    }


    /*
    |--------------------------------------------------------------------------
    | Referral Status
    |--------------------------------------------------------------------------
    |
    | Status is aggregated across the selected period.
    | It is NOT broken down by individual day/month.
    |
    */

    const statusLabels = [
        'Pending',
        'Completed',
        'Refused',
        'Cancelled',
    ];

    const statusData = [
        Number(status.pending || 0),
        Number(status.completed || 0),
        Number(status.refused || 0),
        Number(status.cancelled || 0),
    ];

    const statusTotal = statusData.reduce(
        (total, value) => total + value,
        0
    );

    const hasStatus = statusTotal > 0;

    toggleEmpty(
        'referralStatusChart',
        'referralStatusEmpty',
        hasStatus
    );

    if (hasStatus) {
        makeChart('referralStatusChart', {
            type: 'bar',

            data: {
                labels: statusLabels,

                datasets: [{
                    label: 'Referrals',

                    data: statusData,

                    backgroundColor: [
                        palette.amber,
                        palette.emerald,
                        palette.red,
                        palette.slate,
                    ],

                    borderRadius: 8,
                    borderSkipped: false,
                }],
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: false,
                    },

                    tooltip: sharedTooltip,
                },

                scales: {
                    y: {
                        beginAtZero: true,

                        grid: {
                            color: palette.gridLine,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#94a3b8',
                            precision: 0,
                            maxTicksLimit: 5,
                        },

                        border: {
                            display: false,
                        },
                    },

                    x: {
                        grid: {
                            display: false,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#64748b',
                        },

                        border: {
                            display: false,
                        },
                    },
                },
            },
        });
    } else {
        destroyChart('referralStatusChart');
    }


    /*
    |--------------------------------------------------------------------------
    | Top Referral Destinations
    |--------------------------------------------------------------------------
    */

    const destinations = Array.isArray(
        analytics.destinations
    )
        ? analytics.destinations
        : [];

    const hasDestinations = destinations.length > 0;

    toggleEmpty(
        'referralDestinationsChart',
        'referralDestinationsEmpty',
        hasDestinations
    );

    if (hasDestinations) {
        makeChart('referralDestinationsChart', {
            type: 'bar',

            data: {
                labels: destinations.map(
                    (destination) => destination.label
                ),

                datasets: [{
                    label: 'Referrals',

                    data: destinations.map(
                        (destination) => destination.count
                    ),

                    backgroundColor: palette.emerald,

                    borderRadius: 6,
                    borderSkipped: false,
                }],
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                indexAxis: 'y',

                plugins: {
                    legend: {
                        display: false,
                    },

                    tooltip: sharedTooltip,
                },

                scales: {
                    x: {
                        beginAtZero: true,

                        grid: {
                            color: palette.gridLine,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#94a3b8',
                            precision: 0,
                            maxTicksLimit: 5,
                        },

                        border: {
                            display: false,
                        },
                    },

                    y: {
                        grid: {
                            display: false,
                        },

                        ticks: {
                            font: baseFont,
                            color: '#64748b',
                        },

                        border: {
                            display: false,
                        },
                    },
                },
            },
        });
    } else {
        destroyChart('referralDestinationsChart');
    }


    
}

    renderAnalytics(initialAnalytics);

    const yearSelect = document.getElementById('referralAnalyticsYear');
    const monthSelect = document.getElementById('referralAnalyticsMonth');
    const loading = document.getElementById('referralAnalyticsLoading');

    function loadAnalytics() {
        if (!yearSelect || !monthSelect) return;

        const year = yearSelect.value;
        const month = monthSelect.value;

        if (loading) {
            loading.style.display = 'block';
        }

        const params = new URLSearchParams({
            year: year,
            month: month,
        });

        fetch('{{ route('referrals.analytics') }}?' + params.toString())
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to load referral analytics.');
                }

                return response.json();
            })
            .then((analytics) => {
                renderAnalytics(analytics);
            })
            .catch((error) => {
                console.error('Referral analytics error:', error);
            })
            .finally(() => {
                if (loading) {
                    loading.style.display = 'none';
                }
            });
    }

    if (yearSelect) {
        yearSelect.addEventListener('change', loadAnalytics);
    }
    if (monthSelect) {
        monthSelect.addEventListener('change', loadAnalytics);
    }

});

</script>

{{-- Archive confirmation (referral-specific; mirrors the Prenatal Visits pattern) --}}
<div id="archiveReferralModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 backdrop-blur-sm">
    <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900">Archive Referral?</h3>
                <p class="text-sm text-gray-500">This referral will be moved to Archived Referrals and can be restored later.</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="closeArchiveReferralModal()" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" id="confirmArchiveReferralButton" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                Archive Referral
            </button>
        </div>
    </div>
</div>

<script>
    let pendingArchiveReferralForm = null;

    // Expand/collapse the per-patient referral history (desktop details row
    // and mobile card block; only one of them is visible per breakpoint).
    function toggleReferralHistory(patientId) {
        ['referral-history-', 'referral-history-mobile-'].forEach(function (prefix) {
            const el = document.getElementById(prefix + patientId);
            if (el) el.classList.toggle('hidden');
        });
    }

    function confirmArchiveReferral(button) {
        pendingArchiveReferralForm = button.closest('form');
        const modal = document.getElementById('archiveReferralModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeArchiveReferralModal() {
        const modal = document.getElementById('archiveReferralModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        pendingArchiveReferralForm = null;
    }

    document.getElementById('confirmArchiveReferralButton')?.addEventListener('click', function () {
        if (pendingArchiveReferralForm) {
            pendingArchiveReferralForm.submit();
        }
    });

    document.getElementById('archiveReferralModal')?.addEventListener('click', function (event) {
        if (event.target === this) {
            closeArchiveReferralModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeArchiveReferralModal();
        }
    });
</script>

</x-app-layout>
