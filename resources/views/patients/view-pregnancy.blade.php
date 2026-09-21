<x-app-layout>
    @php
        $outcome = $patient->pregnancyOutcome;
    @endphp

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('view-all-records.history', $patient) }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100" aria-label="Back to patient history">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Pregnancy Record</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</p>
                </div>
            </div>
            <a href="{{ route('view-all-records.pregnancy.print', $patient) }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Print Pregnancy</a>
        </div>

        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900">Pregnancy Details</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-xs font-medium text-gray-500">Status</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->status }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Gravida</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->gravida ?? 'N/A' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Para</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->para ?? 'N/A' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Delivery Date</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->delivery_date?->format('M d, Y') ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">LMP</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->lmp?->format('M d, Y') ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">EDD</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->edd?->format('M d, Y') ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Previous CS</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->previous_cs ? 'Yes' : 'No' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Miscarriage</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->miscarriage ? 'Yes' : 'No' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Birthdate</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->birthdate?->format('M d, Y') ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Age</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->age ?? 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Civil Status</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->civil_status ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">Contact Number</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->contact_number ?: 'Not recorded' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">PhilHealth Member</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->philhealth_member ? 'Yes' : 'No' }}</p></div>
                <div><p class="text-xs font-medium text-gray-500">PhilHealth Number</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->philhealth_number ?: 'Not recorded' }}</p></div>
                <div class="sm:col-span-2"><p class="text-xs font-medium text-gray-500">Address</p><p class="mt-1 font-semibold text-gray-900">{{ $patient->address ?: 'Not recorded' }}</p></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-bold text-gray-900">Prenatal Visits / Checkups</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Date</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">BP</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Risk</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($patient->prenatalVisits as $visit)
                            <tr>
                                <td class="px-5 py-4 text-sm text-gray-900">{{ $visit->visit_date?->format('M d, Y') ?: 'Not recorded' }}</td>
                                <td class="px-5 py-4 text-sm text-gray-900">{{ $visit->bp_sys ?? 'N/A' }}/{{ $visit->bp_dia ?? 'N/A' }}</td>
                                <td class="px-5 py-4 text-sm text-gray-900">{{ $visit->risk_level ?: 'Not recorded' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <button type="button" onclick="document.getElementById('visit-details-{{ $visit->id }}').classList.toggle('hidden')" class="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">View</button>
                                    <a href="{{ route('prenatal-visits.print', $visit) }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Print</a>
                                </td>
                            </tr>
                            <tr id="visit-details-{{ $visit->id }}" class="hidden bg-gray-50">
                                <td colspan="4" class="px-5 py-4 text-sm text-gray-700">
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div><span class="font-semibold">Weight:</span> {{ $visit->weight ?? 'N/A' }} kg</div>
                                        <div><span class="font-semibold">Gestational Age:</span> {{ $visit->gestational_age ?? 'N/A' }} weeks</div>
                                        <div><span class="font-semibold">Temperature:</span> {{ $visit->temperature ?? 'N/A' }}&deg;C</div>
                                        <div><span class="font-semibold">Next Visit:</span> {{ $visit->next_visit_date?->format('M d, Y') ?: 'Not scheduled' }}</div>
                                        <div class="sm:col-span-2 lg:col-span-4"><span class="font-semibold">Assessment:</span> {{ $visit->assessment ?: 'No assessment recorded.' }}</div>
                                        <div class="sm:col-span-2 lg:col-span-4"><span class="font-semibold">Recommendation:</span> {{ $visit->recommendation ?: 'No recommendation recorded.' }}</div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">No prenatal visits were recorded for this pregnancy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900">Pregnancy Outcome</h2>
            @if($outcome)
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                    <div><p class="text-xs font-medium text-gray-500">Outcome</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->outcome_type ?: 'Not recorded' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Delivery Location</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->delivery_location ?: 'Not recorded' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Confirmation Source</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->confirmation_source ?: 'Not recorded' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Confirmed At</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->confirmed_at?->format('M d, Y H:i') ?: 'Not recorded' }}</p></div>
                    <div><p class="text-xs font-medium text-gray-500">Confirmed By</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->confirmedBy?->name ?: 'Not recorded' }}</p></div>
                    <div class="sm:col-span-2 lg:col-span-4"><p class="text-xs font-medium text-gray-500">Notes</p><p class="mt-1 font-semibold text-gray-900">{{ $outcome->notes ?: 'No outcome notes recorded.' }}</p></div>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-500">No pregnancy outcome has been recorded.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900">Baby/Babies</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @forelse($patient->babies as $baby)
                    <div class="rounded-xl border border-gray-100 p-4">
                        <h3 class="font-semibold text-gray-900">Baby {{ $loop->iteration }}{{ $baby->full_name ? ': ' . $baby->full_name : '' }}</h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-gray-500">Sex</dt><dd class="font-medium text-gray-900">{{ $baby->sex ?: 'Not recorded' }}</dd></div>
                            <div><dt class="text-gray-500">Date of Birth</dt><dd class="font-medium text-gray-900">{{ $baby->date_of_birth?->format('M d, Y') ?: 'Not recorded' }}</dd></div>
                            <div><dt class="text-gray-500">Time of Birth</dt><dd class="font-medium text-gray-900">{{ $baby->time_of_birth?->format('h:i A') ?: 'Not recorded' }}</dd></div>
                            <div><dt class="text-gray-500">Birth Weight</dt><dd class="font-medium text-gray-900">{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'Not recorded' }}</dd></div>
                            <div><dt class="text-gray-500">Birth Length</dt><dd class="font-medium text-gray-900">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'Not recorded' }}</dd></div>
                        </dl>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No baby records were recorded for this pregnancy.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
