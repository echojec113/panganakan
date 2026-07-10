<x-app-layout>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-5 bg-gray-50">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-3.28a1 1 0 01-.948-.684L12.5 1h-1L10.23 4.316A1 1 0 019.28 5H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800">Delivered Patients</h1>
                        <p class="text-sm text-gray-500">A list of patients with completed pregnancies.</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('patients.delivered') }}" class="w-full sm:w-72">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search patient..." class="w-full rounded-xl border-gray-200 pr-10 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <button class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-blue-600" type="submit">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </div>
                </form>
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

            @if($patients->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                    <p class="text-sm text-gray-500">No delivered patients found.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Patient</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Total Babies</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Last Delivery</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($patients as $row)
                                @php($patient = $row->patient)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-pink-50 text-pink-600">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</div>
                                                <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-500">
                                                    <span>{{ $patient->age ?: 'N/A' }} years</span>
                                                    <span>&bull;</span>
                                                    <span>{{ $patient->contact_number ?: 'No contact number' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center text-sm font-semibold text-gray-900">{{ $row->completed_pregnancies }}</td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">{{ $row->total_babies }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                        {{ $row->last_delivery_date ? \Carbon\Carbon::parse($row->last_delivery_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <a href="{{ route('patients.delivered.history', $patient->id) }}" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                    </svg>
                                                    View History
                                                </a>
                                                <button type="button"
                                                    onclick="openStartPregnancyModal('{{ route('patients.start-new-pregnancy', $patient->id) }}', '{{ $patient->first_name }} {{ $patient->last_name }}', '{{ $patient->delivery_date ? \Carbon\Carbon::parse($patient->delivery_date)->format('M d, Y') : 'N/A' }}', '{{ $patient->gravida + 1 }}', '{{ $patient->para }}', '{{ $patient->address }}', '{{ $patient->contact_number }}')"
                                                    class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                                    Start New Pregnancy
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-sm text-gray-500">
                    <div>Showing {{ $patients->firstItem() }} to {{ $patients->lastItem() }} of {{ $patients->total() }} patients</div>
                    {{ $patients->links() }}
                </div>
            @endif
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-gray-700">Address</label>
                        <input id="modalAddress" name="address" type="text" class="w-full mt-1 rounded-lg border-gray-300">
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeStartPregnancyModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold">Create New Pregnancy</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStartPregnancyModal(action, name, deliveryDate, gravida, para, address, contact) {
            document.getElementById('startPregnancyForm').action = action;
            document.getElementById('modalPatientName').innerText = name;
            document.getElementById('modalDeliveryDate').value = deliveryDate;
            document.getElementById('modalGravida').value = gravida;
            document.getElementById('modalPara').value = para;
            document.getElementById('modalAddress').value = address;
            document.getElementById('modalContact').value = contact;
            document.getElementById('startPregnancyModal').classList.remove('hidden');
            document.getElementById('startPregnancyModal').classList.add('flex');
        }

        function closeStartPregnancyModal() {
            document.getElementById('startPregnancyModal').classList.add('hidden');
            document.getElementById('startPregnancyModal').classList.remove('flex');
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
