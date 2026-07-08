<x-app-layout>
    
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-5 bg-gray-50">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498A1 1 0 0121 17.72V19a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-800">Pregnancy History</h1>
                            <p class="text-sm text-gray-500">Review completed pregnancy records that remain available as historical patient history.</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $patients->count() }} completed pregnancy {{ Str::plural('record', $patients->count()) }}
                    </div>
                </div>
            </div>

            <div class="p-6">
                @if(session('success'))
                    <div class="mb-6 rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <div class="font-semibold">Please review the highlighted issues.</div>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h2 class="font-semibold text-amber-900">Historical pregnancy records</h2>
                            <p class="mt-1 text-sm text-amber-800">Completed pregnancies are kept as historical records and remain visible even after you start a new pregnancy for the same mother.</p>
                        </div>
                    </div>
                </div>

                @if($patients->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-500">No completed pregnancy records found yet.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Delivery Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Babies</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($patients as $patient)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            {{ $patient->first_name }} {{ $patient->last_name }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-700">
                                            {{ $patient->delivery_date ? \Carbon\Carbon::parse($patient->delivery_date)->format('M d, Y') : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-700">
                                            @if($patient->babies->count() > 0)
                                                <div class="space-y-1">
                                                    @foreach($patient->babies as $baby)
                                                        <div class="flex items-center gap-2">
                                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded-full">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                                </svg>
                                                                {{ $baby->full_name }}
                                                                @if($baby->sex)
                                                                    <span class="text-gray-500">({{ $baby->sex }})</span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs">No baby records</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Historical Record</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-right">
                                            <div class="flex flex-col items-end gap-2">
                                                <div class="flex justify-end gap-2">
                                                    <a href="{{ route('patients.show', ['patient' => $patient->id, 'from' => 'delivered-patients']) }}"
                                                       class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium">
                                                        View Record
                                                    </a>

                                                    <button type="button"
        onclick="openStartPregnancyModal(
            '{{ route('patients.start-new-pregnancy', $patient->id) }}',
            '{{ $patient->first_name }} {{ $patient->last_name }}',
            '{{ $patient->delivery_date ? \Carbon\Carbon::parse($patient->delivery_date)->format('M d, Y') : 'N/A' }}',
            '{{ $patient->gravida + 1 }}',
            '{{ $patient->para }}',
            '{{ $patient->address }}',
            '{{ $patient->contact_number }}',
            '{{ $patient->civil_status }}',
            '{{ $patient->philhealth_member }}',
            '{{ $patient->philhealth_number }}',
            '{{ $patient->previous_cs }}',
            '{{ $patient->miscarriage }}'
        )"
        class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium">
    Start New Pregnancy
</button>
                                                </div>
                                                <p class="max-w-xs text-right text-[11px] leading-5 text-gray-500">
                                                    Creates a new ongoing pregnancy record. This completed record will stay unchanged.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="startPregnancyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl overflow-hidden">
        <div class="bg-indigo-50 px-6 py-5 border-b">
            <h2 class="text-xl font-bold text-gray-800">Start New Pregnancy</h2>
            <p id="modalPatientName" class="text-sm text-gray-600"></p>
        </div>

        <form id="startPregnancyForm" method="POST" class="p-6 space-y-5">
            @csrf

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                This will create a new ongoing pregnancy record. The completed record will stay unchanged.
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">Previous Delivery Date</label>
                    <input id="modalDeliveryDate" type="text" readonly class="w-full mt-1 rounded-lg border-gray-300 bg-gray-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">New Gravida</label>
                    <input id="modalGravida" name="gravida" type="number" readonly class="w-full mt-1 rounded-lg border-gray-300 bg-gray-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">New Para</label>
                    <input id="modalPara" name="para" type="number" readonly class="w-full mt-1 rounded-lg border-gray-300 bg-gray-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">LMP <span class="text-red-500">*</span></label>
                    <input id="modalLmp" name="lmp" type="date" required class="w-full mt-1 rounded-lg border-gray-300">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">EDD <span class="text-red-500">*</span></label>
                    <input id="modalEdd" name="edd" type="date" readonly required class="w-full mt-1 rounded-lg border-gray-300 bg-gray-100">
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700">Contact Number</label>
                    <input id="modalContact" name="contact_number" type="text" class="w-full mt-1 rounded-lg border-gray-300">
                </div>

                <div class="col-span-2">
                    <label class="text-sm font-medium text-gray-700">Address</label>
                    <input id="modalAddress" name="address" type="text" class="w-full mt-1 rounded-lg border-gray-300">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeStartPregnancyModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">
                    Create New Pregnancy
                </button>
            </div>
        </form>
    </div>
</div>

<script>

    
function openStartPregnancyModal(action, name, deliveryDate, gravida, para, address, contact, civilStatus, philhealthMember, philhealthNumber, previousCs, miscarriage) {
    document.getElementById('startPregnancyForm').action = action;
    document.getElementById('modalPatientName').innerText = name;
    document.getElementById('modalDeliveryDate').value = deliveryDate;
    document.getElementById('modalGravida').value = gravida;
    document.getElementById('modalPara').value = para;
    document.getElementById('modalAddress').value = address;
    document.getElementById('modalContact').value = contact;

    const modal = document.getElementById('startPregnancyModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}



function closeStartPregnancyModal() {
    const modal = document.getElementById('startPregnancyModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.addEventListener('DOMContentLoaded', function () {
    const lmpInput = document.getElementById('modalLmp');
    const eddInput = document.getElementById('modalEdd');

    if (lmpInput && eddInput) {
        lmpInput.addEventListener('change', function () {
            if (!this.value) {
                eddInput.value = '';
                return;
            }

            const lmpDate = new Date(this.value);
            lmpDate.setDate(lmpDate.getDate() + 280);

            eddInput.value = lmpDate.toISOString().split('T')[0];
        });
    }
});


</script>
</x-app-layout>