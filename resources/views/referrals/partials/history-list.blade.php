{{-- Per-patient referral history (non-archived only, newest first).
     Rendered inside the expandable history row/card of the Referrals
     index. Every action targets an explicit referral ID so each entry is
     independent; archived referrals never appear (SoftDeletes scope). --}}
<ul class="space-y-3">
    @foreach($patient->referrals as $historyReferral)
        <li class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-gray-700">{{ $historyReferral->referral_date->format('M d, Y') }}</span>
                    <span class="text-xs text-gray-600">{{ $historyReferral->referred_to }}</span>
                    <x-status-badge :variant="$statusVariant($historyReferral->status)">
                        {{ $historyReferral->status }}
                    </x-status-badge>
                    <x-status-badge :variant="$sourceVariant($historyReferral)">
                        {{ $sourceLabel($historyReferral) }}
                    </x-status-badge>
                </div>

                <div class="flex items-center gap-2" onclick="event.stopPropagation()">
                    <a href="{{ route('referrals.show', $historyReferral->id) }}"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-md hover:bg-gray-50 transition">
                        View
                    </a>
                    <a href="{{ route('referrals.print', $historyReferral->id) }}"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-gray-200 text-gray-700 text-xs font-semibold rounded-md hover:bg-gray-50 transition">
                        Print
                    </a>
                    @if(auth()->user()->role === 'staff')
                        <form action="{{ route('referrals.destroy', $historyReferral->id) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="confirmArchiveReferral(this); event.stopPropagation();"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-red-700 hover:bg-red-50 hover:text-red-900 transition-all duration-150"
                                title="Archive" aria-label="Archive referral">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <p class="mt-1.5 text-xs text-gray-500 line-clamp-2">{{ \Str::limit($historyReferral->reason, 80) }}</p>

            @if($historyReferral->prenatal_visit_id)
                <p class="text-[11px] text-gray-400 mt-0.5">Visit: {{ $historyReferral->prenatalVisit?->visit_date?->format('M d, Y') ?? $historyReferral->referral_date->format('M d, Y') }}</p>
            @endif

            @if($historyReferral->status === 'Refused' && $historyReferral->refusal_recorded_at)
                <p class="text-[11px] text-gray-400 mt-0.5">Recorded {{ $historyReferral->refusal_recorded_at->format('M d') }}</p>
            @elseif($historyReferral->status === 'Completed' && $historyReferral->completed_at)
                <p class="text-[11px] text-gray-400 mt-0.5">Completed {{ $historyReferral->completed_at->format('M d, Y') }}</p>
            @endif
        </li>
    @endforeach
</ul>
