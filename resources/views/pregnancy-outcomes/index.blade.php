<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <x-icon-title
                        title="Pregnancy Outcome Monitoring"
                        subtitle="Track pregnancies nearing their expected delivery date, follow-up observations, and confirmed outcomes. A passed due date never marks a pregnancy as delivered."
                    >
                        <x-slot name="icon">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </x-slot>
                    </x-icon-title>
                </div>
                <form method="GET" action="{{ route('pregnancy-outcomes.index') }}" class="w-full lg:w-72">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search patient..." class="w-full rounded-xl border-gray-200 pr-10 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-blue-600" type="submit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                        <button type="submit" class="hidden">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mb-6">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-amber-900">Action Required</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-600 px-2 py-0.5 text-xs font-bold text-white"><span class="h-1.5 w-1.5 rounded-full bg-white"></span> Outcome Confirmation</span>
                        </div>
                        <div class="mt-2 flex items-end justify-between">
                            <span class="text-2xl font-extrabold text-amber-800">{{ $stats[\App\Services\PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED] ?? 0 }}</span>
                            <span class="text-xs text-amber-700">EDD passed, outcome unconfirmed</span>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-green-200 bg-green-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-green-800">Still Pregnant</span>
                            <span class="inline-flex items-center justify-center h-7 min-w-7 rounded-full bg-green-600 px-2 text-sm font-bold text-white">{{ $stats[\App\Services\PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED] ?? 0 }}</span>
                        </div>
                        <p class="mt-1 text-xs text-green-700">Recently confirmed by follow-up</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-rose-800">Unable to Contact</span>
                            <span class="inline-flex items-center justify-center h-7 min-w-7 rounded-full bg-rose-600 px-2 text-sm font-bold text-white">{{ $stats[\App\Services\PregnancyOutcomeMonitoringService::STATE_UNABLE_TO_CONTACT] ?? 0 }}</span>
                        </div>
                        <p class="mt-1 text-xs text-rose-700">Recent follow-up attempt was unsuccessful.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Confirmed Deliveries</span>
                            <span class="inline-flex items-center justify-center h-7 min-w-7 rounded-full bg-gray-300 px-2 text-sm font-bold text-gray-700">{{ $stats[\App\Services\PregnancyOutcomeMonitoringService::STATE_RESOLVED] ?? 0 }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Outcomes recorded with provenance</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-relaxed text-blue-900">
                    <span class="font-semibold">How to read this page:</span>
                    Passing the EDD never marks a pregnancy as delivered. Once the EDD has passed and no outcome is confirmed, the pregnancy enters <span class="font-semibold">Outcome Confirmation Required</span> — record whether the patient is still pregnant or could not be reached. Delivery is confirmed only through the explicit delivery workflow in the patient profile.
                </div>
            </div>
        </div>

        <x-flash type="success" :message="session('success')" class="mb-4" />
        <x-error-summary :errors="$errors" class="mb-6" />

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 bg-gray-50 flex flex-wrap items-center gap-2">
                <a href="{{ route('pregnancy-outcomes.index') }}" class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-medium {{ $stateFilter === '' ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">All</a>
                @foreach(\App\Services\PregnancyOutcomeMonitoringService::STATE_FILTERS as $slug => $stateKey)
                    <a href="{{ route('pregnancy-outcomes.index', ['state' => $slug, 'search' => request('search')]) }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium {{ $stateFilter === $stateKey ? 'bg-primary text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                        {{ \App\Services\PregnancyOutcomeMonitoringService::stateLabel($stateKey) }}
                    </a>
                @endforeach
            </div>

            <div class="p-6">
                @if($paginator->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-12 text-center">
                        <p class="text-sm text-gray-500">No pregnancies match these criteria.</p>
                    </div>
                @else
                    <div class="mb-4 hidden lg:block text-xs font-semibold uppercase tracking-wide text-gray-400">Desktop view</div>

                    @php($monitoringReturnUrl = route('pregnancy-outcomes.index', request()->query()))

                    {{-- Desktop table --}}
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">EDD</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Monitoring State</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Latest Follow-up</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($paginator->items() as $row)
                                    @php($patient = $row['patient'])
                                    <tr class="hover:bg-gray-50 transition {{ in_array($row['state'], [\App\Services\PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED, \App\Services\PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED], true) ? 'opacity-70' : '' }}">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-pink-50 text-pink-600">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <a href="{{ route('patients.show', ['patient' => $patient->id, 'return' => $monitoringReturnUrl]) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-600">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</a>
                                                    <div class="mt-0.5 text-xs text-gray-500">G{{ $patient->gravida }} P{{ $patient->para }} &bull; {{ $patient->contact_number ?: 'No contact' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            @if($patient->edd)
                                                <span class="font-medium">EDD: {{ $patient->edd->format('M d, Y') }}</span>
                                                @if($row['days_until_edd'] !== null && $row['days_until_edd'] < 0)
                                                    <div class="text-xs font-semibold text-amber-700">{{ abs($row['days_until_edd']) }} days past EDD</div>
                                                @else
                                                    <div class="text-xs text-gray-500">{{ $row['days_until_edd'] !== null ? $row['days_until_edd'] . ' days until EDD' : '' }}</div>
                                                @endif
                                            @else
                                                <span class="text-gray-400">EDD: N/A</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900">{{ $row['status_label'] }}</td>
                                        <td class="px-4 py-4">
                                            <x-status-badge class="{{ $row['state_badge_class'] }}">{{ $row['state_label'] }}</x-status-badge>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-700">
                                            @if($row['state'] === \App\Services\PregnancyOutcomeMonitoringService::STATE_RESOLVED)
                                                <span class="font-medium text-gray-900">{{ $row['confirmed_at']?->format('M d, Y') }}</span>
                                                <span class="block text-xs text-gray-500">Delivered @if($row['delivery_location_label']) at {{ $row['delivery_location_label'] }} @endif</span>
                                                @if($row['confirmation_source_label'])
                                                    <span class="block text-xs text-gray-500">Source: {{ $row['confirmation_source_label'] }}</span>
                                                @endif
                                            @elseif($row['last_follow_up_at'])
                                                <span class="font-medium text-gray-900">{{ $row['last_follow_up_label'] }}</span>
                                                <span class="block text-xs text-gray-500">{{ $row['last_follow_up_at']->format('M d, Y H:i') }} @if($row['last_follow_up_by']) by {{ $row['last_follow_up_by'] }} @endif</span>
                                            @else
                                                <span class="text-gray-400">None recorded</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                @if($row['follow_up_observable'] && auth()->user()->role !== 'admin')
                                                    <button type="button"
                                                            data-outcome-confirm-trigger
                                                            data-outcome-tone="confirm"
                                                            data-outcome-title="Confirm Still Pregnant"
                                                            data-outcome-message="Record that follow-up confirmed the patient is still pregnant as of today. This observation remains current for the monitoring window and does not mark the pregnancy as delivered."
                                                            data-outcome-confirm-label="Confirm Still Pregnant"
                                                            data-outcome-patient="{{ $patient->first_name }} {{ $patient->last_name }}"
                                                            data-outcome-action="{{ route('pregnancy-outcomes.still-pregnant', $patient->id) }}"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Confirm Still Pregnant
                                                    </button>
                                                    <button type="button"
                                                            data-outcome-confirm-trigger
                                                            data-outcome-tone="alert"
                                                            data-outcome-title="Record Unable to Contact"
                                                            data-outcome-message="Record that a follow-up attempt was made but the patient could not be reached. This does not mark the pregnancy as delivered and does not change referral or clinical risk status."
                                                            data-outcome-confirm-label="Record Unable to Contact"
                                                            data-outcome-patient="{{ $patient->first_name }} {{ $patient->last_name }}"
                                                            data-outcome-action="{{ route('pregnancy-outcomes.unable-to-contact', $patient->id) }}"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                                                        </svg>
                                                        Unable to Contact
                                                    </button>
                                                @endif
                                                @if($row['state'] === \App\Services\PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED)
                                                    <a href="{{ route('patients.delivered.history', $patient->id) }}"
                                                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                        Pregnancy History
                                                    </a>
                                                @endif
                                                <a href="{{ route('patients.show', ['patient' => $patient->id, 'return' => $monitoringReturnUrl]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    {{ $patient->status === 'DELIVERED' ? 'View Record' : 'Open Profile' }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="lg:hidden space-y-4">
                        @foreach($paginator->items() as $row)
                            @php($patient = $row['patient'])
                            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm p-4 {{ in_array($row['state'], [\App\Services\PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED, \App\Services\PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED], true) ? 'opacity-70' : '' }}">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-pink-50 text-pink-600 shrink-0">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('patients.show', ['patient' => $patient->id, 'return' => $monitoringReturnUrl]) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-600">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</a>
                                        <div class="mt-0.5 text-xs text-gray-500">G{{ $patient->gravida }} P{{ $patient->para }}</div>
                                    </div>
                                    <x-status-badge class="{{ $row['state_badge_class'] }}">{{ $row['state_label'] }}</x-status-badge>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-500">EDD</div>
                                        <div class="font-medium text-gray-900">{{ $patient->edd ? $patient->edd->format('M d, Y') : 'N/A' }}</div>
                                        @if($row['days_until_edd'] !== null)
                                            <div class="text-xs {{ $row['days_until_edd'] < 0 ? 'font-semibold text-amber-700' : 'text-gray-500' }}">{{ $row['days_until_edd'] < 0 ? abs($row['days_until_edd']) . ' days past EDD' : $row['days_until_edd'] . ' days until EDD' }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Status</div>
                                        <div class="font-medium text-gray-900">{{ $row['status_label'] }}</div>
                                    </div>
                                </div>
                                @if($row['state'] === \App\Services\PregnancyOutcomeMonitoringService::STATE_RESOLVED && $row['delivery_location_label'])
                                    <div class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">Delivered at {{ $row['delivery_location_label'] }}</div>
                                @endif
                                <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-3">
                                    @if($row['follow_up_observable'] && auth()->user()->role !== 'admin')
                                        <button type="button"
                                                data-outcome-confirm-trigger
                                                data-outcome-tone="confirm"
                                                data-outcome-title="Confirm Still Pregnant"
                                                data-outcome-message="Record that follow-up confirmed the patient is still pregnant as of today. This observation remains current for the monitoring window and does not mark the pregnancy as delivered."
                                                data-outcome-confirm-label="Confirm Still Pregnant"
                                                data-outcome-patient="{{ $patient->first_name }} {{ $patient->last_name }}"
                                                data-outcome-action="{{ route('pregnancy-outcomes.still-pregnant', $patient->id) }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Still Pregnant
                                        </button>
                                        <button type="button"
                                                data-outcome-confirm-trigger
                                                data-outcome-tone="alert"
                                                data-outcome-title="Record Unable to Contact"
                                                data-outcome-message="Record that a follow-up attempt was made but the patient could not be reached. This does not mark the pregnancy as delivered and does not change referral or clinical risk status."
                                                data-outcome-confirm-label="Record Unable to Contact"
                                                data-outcome-patient="{{ $patient->first_name }} {{ $patient->last_name }}"
                                                data-outcome-action="{{ route('pregnancy-outcomes.unable-to-contact', $patient->id) }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                                            </svg>
                                            Unable to Contact
                                        </button>
                                    @endif
                                    <a href="{{ route('patients.show', ['patient' => $patient->id, 'return' => $monitoringReturnUrl]) }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        {{ $patient->status === 'DELIVERED' ? 'View Record' : 'Open Profile' }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
                        <div>Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} pregnancies</div>
                        {{ $paginator->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @php($hasFollowUpRows = collect($paginator->items())->contains(fn (array $row) => $row['follow_up_observable'] === true))
    @if($hasFollowUpRows && auth()->user()->role !== 'admin')
        <x-outcome-confirm-modal />
    @endif
</x-app-layout>