<x-app-layout>
    <style>
        .ca-theme {
            --ca-primary: #55B85A;
            --ca-primary-hover: #4aa04c;
            --ca-soft: #F6F4EE;
            --ca-border: #E7E9E5;
            --ca-text: #19355F;
            --ca-muted: #657083;
        }

        .ca-theme .ca-card,
        .ca-theme .ca-modal,
        .ca-theme .ca-table-head {
            border-color: var(--ca-border);
        }

        .ca-theme .ca-table-head {
            background-color: var(--ca-soft);
        }

        .ca-theme .ca-row:hover {
            background-color: var(--ca-soft);
        }

        .ca-theme .ca-name { color: var(--ca-text); }
        .ca-theme .ca-muted { color: var(--ca-muted); }

        .ca-theme .ca-tab {
            border-color: var(--ca-border);
            color: var(--ca-muted);
            background-color: #fff;
        }

        .ca-theme .ca-tab:hover {
            background-color: var(--ca-soft);
            color: var(--ca-text);
        }

        .ca-theme .ca-tab.active {
            background-color: var(--ca-primary);
            border-color: var(--ca-primary);
            color: #fff;
        }

        .ca-theme .ca-input {
            width: 100%;
            border: 1px solid var(--ca-border);
            background-color: #fff;
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            font-size: 0.875rem;
            color: #1f2937;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .ca-theme .ca-input:focus {
            border-color: var(--ca-primary);
            box-shadow: 0 0 0 3px rgba(85, 184, 90, 0.2);
        }

        .ca-theme .ca-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--ca-text);
            margin-bottom: 0.375rem;
        }

        .ca-theme .ca-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--ca-primary);
            color: #fff;
            border-radius: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            transition: background-color 0.15s;
        }

        .ca-theme .ca-btn-primary:hover {
            background-color: var(--ca-primary-hover);
        }

        .ca-theme .ca-btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .ca-theme .ca-btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--ca-border);
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            background-color: #fff;
            transition: background-color 0.15s;
        }

        .ca-theme .ca-btn-secondary:hover {
            background-color: var(--ca-soft);
        }

        .ca-theme .ca-btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #B42318;
            color: #fff;
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            transition: background-color 0.15s;
        }

        .ca-theme .ca-btn-danger:hover {
            background-color: #912018;
        }

        .ca-theme .ca-slot-chip {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--ca-border);
            border-radius: 0.5rem;
            background-color: #fff;
            padding: 0.5rem 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--ca-text);
            cursor: pointer;
            user-select: none;
            transition: background-color 0.12s, border-color 0.12s, color 0.12s;
        }

        .ca-theme .ca-slot-chip:hover {
            background-color: var(--ca-soft);
        }

        .ca-theme .ca-slot-chip.selected {
            background-color: var(--ca-primary);
            border-color: var(--ca-primary);
            color: #fff;
        }

        .ca-theme .ca-error {
            color: #B42318;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .ca-theme .ca-modal button { min-height: 44px; white-space: normal; }

        @media (max-width: 640px) {
            .ca-theme .ca-modal { padding: 20px; }
            .ca-theme .ca-modal-actions { flex-direction: column; }
            .ca-theme .ca-modal-actions > * { width: 100%; }
        }
    </style>

    <div class="ca-theme mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <x-app-header
            title="Clinic Availability"
            subtitle="Close the clinic for full days, date ranges, or individual time slots"
            class="mb-6"
        />

        <x-flash type="success" :message="session('success')" class="mb-6" />
        <x-flash type="error" :message="session('error')" class="mb-6" />
        <x-flash type="warning" :message="session('warning')" class="mb-6" />

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Please fix the following:</p>
                <ul class="mt-1 list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===================== CREATE CARD ===================== --}}
        <form id="createClosureForm" method="POST" action="{{ route('admin.clinic-unavailability.store') }}" class="ca-card mb-8 rounded-2xl border bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="mode" id="createMode" value="{{ old('mode', 'single_day') }}">
            <input type="hidden" name="confirm_affected" id="createConfirmAffected" value="0">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="ca-name text-base font-semibold">Add Closure</h2>
                    <p class="ca-muted mt-1 text-sm">Existing appointments are never cancelled automatically - you will be asked to confirm first.</p>
                </div>
            </div>

            {{-- MODE TABS --}}
            <div class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Closure type">
                @foreach ([
                    'single_day' => 'Single Day',
                    'date_range' => 'Date Range',
                    'slots' => 'Time Slots',
                ] as $modeKey => $modeLabel)
                    <button type="button"
                            class="ca-tab inline-flex items-center rounded-xl border px-4 py-2 text-sm font-medium transition {{ old('mode', 'single_day') === $modeKey ? 'active' : '' }}"
                            data-mode="{{ $modeKey }}"
                            onclick="setCreateMode('{{ $modeKey }}')">
                        {{ $modeLabel }}
                    </button>
                @endforeach
            </div>

            {{-- SINGLE DAY --}}
            <div id="mode-single_day" class="ca-mode-panel mt-5 hidden">
                <label class="ca-label" for="create-date">Date to close</label>
                <input type="date" id="create-date" name="date" class="ca-input max-w-xs"
                       value="{{ old('date') }}" required>
            </div>

            {{-- DATE RANGE --}}
            <div id="mode-date_range" class="ca-mode-panel mt-5 hidden">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ca-label" for="create-start_date">First day closed</label>
                        <input type="date" id="create-start_date" name="start_date" class="ca-input"
                               value="{{ old('start_date') }}">
                    </div>
                    <div>
                        <label class="ca-label" for="create-end_date">Last day closed (inclusive)</label>
                        <input type="date" id="create-end_date" name="end_date" class="ca-input"
                               value="{{ old('end_date') }}">
                    </div>
                </div>
                <p class="ca-muted mt-2 text-xs">The clinic is closed all day on every date in the range (maximum 366 days).</p>
            </div>

            {{-- TIME SLOTS --}}
            <div id="mode-slots" class="ca-mode-panel mt-5 hidden">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ca-label" for="create-slots-date">Date</label>
                        <input type="date" id="create-slots-date" name="date" class="ca-input max-w-xs"
                               value="{{ old('date') }}">
                    </div>
                </div>

                <p class="ca-label mt-4">Select the 30-minute slots to close</p>
                <p class="ca-muted mb-3 text-xs">Contiguous selections become one window; gaps create separate windows. The clinic day runs 8:00 AM to 5:00 PM.</p>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-9" id="slotGrid">
                    @foreach ($slotStarts as $start)
                        <label class="ca-slot-chip" data-slot="{{ $start }}">
                            <input type="checkbox" name="slot_starts[]" value="{{ $start }}"
                                   class="sr-only"
                                   {{ in_array($start, (array) old('slot_starts', []), true) ? 'checked' : '' }}>
                            {{ $slotLabels[$start] }}
                        </label>
                    @endforeach
                </div>

                <p id="slotSelectionSummary" class="ca-muted mt-3 text-xs"></p>
            </div>

            {{-- REASON --}}
            <div class="mt-5 max-w-xl">
                <label class="ca-label" for="create-reason">Reason (staff-facing note, optional)</label>
                <input type="text" id="create-reason" name="reason" maxlength="255" class="ca-input"
                       placeholder="e.g. Holiday / clinic maintenance"
                       value="{{ old('reason') }}">
                <p class="ca-muted mt-1 text-xs">This note is visible only to Admin and Staff - patients never see it.</p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="submit" id="createSubmitButton" class="ca-btn-primary">
                    Save Closure
                </button>
                <span id="createBusy" class="ca-muted hidden text-sm">Checking existing appointments&hellip;</span>
            </div>
        </form>

        {{-- ===================== CLOSURES TABLE ===================== --}}
        <div class="ca-card rounded-2xl border bg-white shadow-sm">
            <div class="border-b px-6 py-4">
                <h2 class="ca-name text-base font-semibold">Scheduled Closures</h2>
                <p class="ca-muted mt-1 text-sm">Full-day ranges and individual time windows currently blocking new bookings.</p>
            </div>

            @if ($closures->isEmpty())
                <p class="ca-muted px-6 py-10 text-center text-sm">No clinic closures scheduled.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="ca-table-head ca-muted text-xs uppercase tracking-wide">
                                <th class="px-6 py-3">Period</th>
                                <th class="px-6 py-3">Window</th>
                                <th class="px-6 py-3">Reason</th>
                                <th class="px-6 py-3">Created by</th>
                                <th class="px-6 py-3">Created</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($closures as $closure)
                                <tr class="ca-row">
                                    <td class="px-6 py-3 font-medium ca-name whitespace-nowrap">
                                        @if ($closure->start_date->toDateString() === $closure->end_date->toDateString())
                                            {{ $closure->start_date->format('M j, Y') }}
                                        @else
                                            {{ $closure->start_date->format('M j') }} &ndash; {{ $closure->end_date->format('M j, Y') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($closure->all_day)
                                            <span class="inline-flex items-center rounded-full bg-[#EDF5EC] px-2.5 py-0.5 text-xs font-semibold text-[#367E4B]">Full day</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                                {{ app(\App\Services\ClinicAvailabilityService::class)->timeWindowLabel($closure->start_time, $closure->end_time) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 ca-muted">{{ $closure->reason ?? '—' }}</td>
                                    <td class="px-6 py-3 ca-muted whitespace-nowrap">{{ $closure->creator?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 ca-muted whitespace-nowrap">{{ $closure->created_at->format('M j, Y') }}</td>
                                    <td class="px-6 py-3 text-right whitespace-nowrap">
                                        <button type="button"
                                                class="ca-btn-secondary mr-2"
                                                data-id="{{ $closure->id }}"
                                                data-url="{{ route('admin.clinic-unavailability.update', $closure) }}"
                                                data-start-date="{{ $closure->start_date->toDateString() }}"
                                                data-end-date="{{ $closure->end_date->toDateString() }}"
                                                data-all-day="{{ $closure->all_day ? '1' : '0' }}"
                                                data-start-time="{{ $closure->start_time }}"
                                                data-end-time="{{ $closure->end_time }}"
                                                data-reason="{{ $closure->reason }}"
                                                onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <button type="button"
                                                class="ca-btn-danger"
                                                data-url="{{ route('admin.clinic-unavailability.destroy', $closure) }}"
                                                data-period="@if ($closure->start_date->toDateString() === $closure->end_date->toDateString()){{ $closure->start_date->format('M j, Y') }}@else{{ $closure->start_date->format('M j') }} - {{ $closure->end_date->format('M j, Y') }}@endif"
                                                onclick="openRemoveModal(this)">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4">
                    {{ $closures->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== AFFECTED-PREVIEW MODAL ===================== --}}
    <div id="affectedModal" role="dialog" aria-modal="true" aria-labelledby="affectedTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="ca-modal mx-4 w-full max-w-md rounded-xl border bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div id="affectedIcon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#EDF5EC] text-[#367E4B]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 id="affectedTitle" class="ca-name text-base font-semibold">Existing Appointments Affected</h2>
                    <p id="affectedMessage" class="ca-muted mt-1 text-sm leading-6"></p>
                    <dl id="affectedBreakdown" class="mt-4 space-y-2 text-sm"></dl>
                    <p id="affectedOverlap" class="ca-muted mt-3 hidden rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700"></p>
                    <p class="ca-muted mt-3 text-xs leading-5">
                        Existing appointments are never cancelled or rescheduled automatically. Closing this window only prevents new bookings.
                    </p>
                </div>
            </div>

            <div class="ca-modal-actions mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeAffectedModal()"
                        class="ca-btn-secondary">
                    Cancel
                </button>
                <button type="button" id="affectedConfirmButton" onclick="confirmAffectedModal()"
                        class="ca-btn-primary">
                    Confirm &amp; Save
                </button>
            </div>
        </div>
    </div>

    {{-- ===================== EDIT MODAL ===================== --}}
    <div id="editModal" role="dialog" aria-modal="true" aria-labelledby="editTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <form id="editClosureForm" method="POST" class="ca-modal mx-4 w-full max-w-lg rounded-xl border bg-white p-6 shadow-xl">
            @csrf
            @method('PATCH')
            <input type="hidden" name="confirm_affected" id="editConfirmAffected" value="0">

            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="editTitle" class="ca-name text-base font-semibold">Edit Closure</h2>
                    <p class="ca-muted mt-1 text-sm">Adjust the window, times, or reason. The affected-appointment check re-runs on save.</p>
                </div>
                <button type="button" onclick="closeEditModal()"
                        aria-label="Close edit dialog"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-5 space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ca-label" for="edit-start_date">Start date</label>
                        <input type="date" id="edit-start_date" name="start_date" class="ca-input" required>
                    </div>
                    <div>
                        <label class="ca-label" for="edit-end_date">End date (inclusive)</label>
                        <input type="date" id="edit-end_date" name="end_date" class="ca-input" required>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                    <input type="checkbox" id="edit-all_day" name="all_day" value="1" class="rounded border-gray-300 text-[#55B85A] focus:ring-[#55B85A]">
                    Full day (no time window)
                </label>

                <div id="edit-time-fields" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="ca-label" for="edit-start_time">Window start</label>
                        <select id="edit-start_time" name="start_time" class="ca-input">
                            @foreach ($slotStarts as $start)
                                <option value="{{ $start }}">{{ $slotLabels[$start] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ca-label" for="edit-end_time">Window end (exclusive)</label>
                        <select id="edit-end_time" name="end_time" class="ca-input">
                            @foreach ($slotStarts as $start)
                                <option value="{{ $slotEnds[$start] }}">{{ app(\App\Services\ClinicAvailabilityService::class)->timeLabel($slotEnds[$start]) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="ca-label" for="edit-reason">Reason (staff-facing note, optional)</label>
                    <input type="text" id="edit-reason" name="reason" maxlength="255" class="ca-input">
                </div>
            </div>

            <div class="ca-modal-actions mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="ca-btn-secondary">Cancel</button>
                <button type="submit" id="editSubmitButton" class="ca-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    {{-- ===================== REMOVE MODAL ===================== --}}
    <div id="removeModal" role="dialog" aria-modal="true" aria-labelledby="removeTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <form id="removeClosureForm" method="POST" class="ca-modal mx-4 w-full max-w-md rounded-xl border bg-white p-6 shadow-xl">
            @csrf
            @method('DELETE')

            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FDECEC] text-[#B42318]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 id="removeTitle" class="ca-name text-base font-semibold">Remove Closure</h2>
                    <p id="removeMessage" class="ca-muted mt-1 text-sm leading-6">
                        Removing this closure reopens the window for new bookings.
                    </p>
                    <p class="ca-muted mt-2 text-xs leading-5">
                        Other overlapping closures still apply. Existing appointments are never rescheduled automatically.
                    </p>
                </div>
            </div>

            <div class="ca-modal-actions mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRemoveModal()" class="ca-btn-secondary">Cancel</button>
                <button type="submit" class="ca-btn-danger">Remove Closure</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const AFFECTED_URL = @json(route('admin.clinic-unavailability.affected'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const createForm = document.getElementById('createClosureForm');
            const createModeInput = document.getElementById('createMode');
            const createConfirmInput = document.getElementById('createConfirmAffected');
            const createSubmitButton = document.getElementById('createSubmitButton');
            const createBusy = document.getElementById('createBusy');

            const editForm = document.getElementById('editClosureForm');
            const editConfirmInput = document.getElementById('editConfirmAffected');
            const editSubmitButton = document.getElementById('editSubmitButton');
            const editAllDay = document.getElementById('edit-all_day');
            const editTimeFields = document.getElementById('edit-time-fields');

            const editModal = document.getElementById('editModal');
            const removeModal = document.getElementById('removeModal');
            const affectedModal = document.getElementById('affectedModal');

            // Whichever form is waiting on the preview confirmation.
            let pendingForm = null;

            // ---------- mode tabs ----------
            window.setCreateMode = function (mode) {
                createModeInput.value = mode;

                document.querySelectorAll('.ca-tab[data-mode]').forEach(function (tab) {
                    tab.classList.toggle('active', tab.dataset.mode === mode);
                });

                document.querySelectorAll('.ca-mode-panel').forEach(function (panel) {
                    panel.classList.add('hidden');
                });

                document.getElementById('mode-' + mode)?.classList.remove('hidden');
            };

            // Re-apply persisted mode (old input) on load.
            setCreateMode(createModeInput.value || 'single_day');

            // ---------- slot picker ----------
            window.onSlotToggle = function () {
                const checked = document.querySelectorAll('#slotGrid input:checked');
                const summary = document.getElementById('slotSelectionSummary');

                if (checked.length === 0) {
                    summary.textContent = '';
                    return;
                }

                const windows = groupContiguousSlots(readSelectedSlots());
                summary.textContent =
                    checked.length + ' slot' + (checked.length === 1 ? '' : 's') +
                    ' selected - saved as ' + windows.length +
                    ' window' + (windows.length === 1 ? '' : 's') + '.';
            };

            document.querySelectorAll('#slotGrid .ca-slot-chip').forEach(function (chip) {
                const input = chip.querySelector('input');
                chip.classList.toggle('selected', input.checked);
                input.addEventListener('change', function () {
                    chip.classList.toggle('selected', input.checked);
                    onSlotToggle();
                });
            });

            onSlotToggle();

            // ---------- helpers ----------
            function readSelectedSlots() {
                return Array.from(document.querySelectorAll('#slotGrid input:checked'))
                    .map(function (input) { return input.value; })
                    .sort();
            }

            function toMinutes(hhmm) {
                const parts = hhmm.split(':');
                return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            }

            function toHhMm(minutes) {
                const h = Math.floor(minutes / 60);
                const m = minutes % 60;
                return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            }

            // Mirror of ClinicAvailabilityService::windowsFromSlotStarts -
            // used ONLY to build preview requests; the server regroups
            // authoritatively at save time.
            function groupContiguousSlots(starts) {
                if (starts.length === 0) return [];

                const windows = [];
                let runStart = starts[0];
                let runEnd = toMinutes(starts[0]) + 30;

                for (let i = 1; i < starts.length; i++) {
                    const minutes = toMinutes(starts[i]);
                    if (minutes === runEnd) {
                        runEnd = minutes + 30;
                        continue;
                    }
                    windows.push({ start: runStart, end: toHhMm(runEnd) });
                    runStart = starts[i];
                    runEnd = minutes + 30;
                }

                windows.push({ start: runStart, end: toHhMm(runEnd) });
                return windows;
            }

            function readCreateWindows() {
                const mode = createModeInput.value;

                if (mode === 'single_day') {
                    const date = document.getElementById('create-date').value;
                    return date ? [{ start_date: date, end_date: date, all_day: '1' }] : [];
                }

                if (mode === 'date_range') {
                    const start = document.getElementById('create-start_date').value;
                    const end = document.getElementById('create-end_date').value;
                    return (start && end) ? [{ start_date: start, end_date: end, all_day: '1' }] : [];
                }

                // slots
                const date = document.getElementById('create-slots-date').value;
                if (!date) return [];

                return groupContiguousSlots(readSelectedSlots()).map(function (window) {
                    return {
                        start_date: date,
                        end_date: date,
                        all_day: '0',
                        start_time: window.start,
                        end_time: window.end,
                    };
                });
            }

            async function fetchPreview(win, ignoreId) {
                const url = new URL(AFFECTED_URL, location.origin);
                url.searchParams.set('start_date', win.start_date);
                url.searchParams.set('end_date', win.end_date);
                url.searchParams.set('all_day', win.all_day);
                if (win.start_time) url.searchParams.set('start_time', win.start_time);
                if (win.end_time) url.searchParams.set('end_time', win.end_time);
                if (ignoreId) url.searchParams.set('ignore', String(ignoreId));

                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Preview request failed.');
                }

                return response.json();
            }

            async function collectPreview(windows, ignoreId) {
                const totals = {
                    confirmed_count: 0,
                    completed_count: 0,
                    occupying_count: 0,
                    pending_count: 0,
                    overlapping_count: 0,
                };

                for (const win of windows) {
                    const data = await fetchPreview(win, ignoreId);
                    totals.confirmed_count += data.confirmed_count;
                    totals.completed_count += data.completed_count;
                    totals.occupying_count += data.occupying_count;
                    totals.pending_count += data.pending_count;
                    // Overlap count may count one multi-window closure more
                    // than once - it is an informational warning only.
                    totals.overlapping_count += data.overlapping_count;
                }

                return totals;
            }

            function setBusy(isBusy) {
                createBusy.classList.toggle('hidden', !isBusy);
                createSubmitButton.disabled = isBusy;
                editSubmitButton.disabled = isBusy;
            }

            // ---------- preview modal ----------
            function openAffectedModal(totals, form) {
                pendingForm = form;

                const message = document.getElementById('affectedMessage');
                const breakdown = document.getElementById('affectedBreakdown');
                const overlap = document.getElementById('affectedOverlap');
                const confirmButton = document.getElementById('affectedConfirmButton');
                const icon = document.getElementById('affectedIcon');

                const rows = [];

                if (totals.occupying_count > 0) {
                    message.textContent =
                        'This closure overlaps ' + totals.occupying_count +
                        ' existing appointment(s). Closing the window does NOT cancel or reschedule them.';

                    rows.push(['Confirmed &amp; completed (blocking)', totals.occupying_count]);

                    if (totals.completed_count > 0) {
                        rows.push(['of which completed', totals.completed_count]);
                    }
                } else {
                    message.textContent = 'No confirmed or completed appointments fall inside this window.';
                }

                if (totals.pending_count > 0) {
                    rows.push(['Pending requests (informational)', totals.pending_count]);
                }

                breakdown.innerHTML = rows.map(function (row) {
                    return '<div class="flex items-center justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">' +
                        '<dt class="font-medium text-gray-700">' + row[0] + '</dt>' +
                        '<dd class="font-semibold ca-name">' + row[1] + '</dd></div>';
                }).join('');

                if (totals.overlapping_count > 0) {
                    overlap.textContent = 'Note: ' + totals.overlapping_count +
                        ' existing closure record(s) overlap this period. The overlap is allowed - the slots stay closed by every matching closure.';
                    overlap.classList.remove('hidden');
                } else {
                    overlap.classList.add('hidden');
                }

                if (totals.occupying_count > 0) {
                    confirmButton.textContent = 'Confirm & Save';
                    confirmButton.className = 'ca-btn-primary';
                    icon.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FDECEC] text-[#B42318]';
                } else {
                    confirmButton.textContent = 'Save Closure';
                    confirmButton.className = 'ca-btn-primary';
                    icon.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#EDF5EC] text-[#367E4B]';
                }

                affectedModal.classList.remove('hidden');
                affectedModal.classList.add('flex');
                document.body.style.overflow = 'hidden';

                // Stash the confirm requirement for confirmAffectedModal().
                affectedModal.dataset.needsConfirm = totals.occupying_count > 0 ? '1' : '0';
            }

            window.closeAffectedModal = function () {
                affectedModal.classList.add('hidden');
                affectedModal.classList.remove('flex');
                affectedModal.dataset.needsConfirm = '0';
                pendingForm = null;
                document.body.style.overflow = '';
            };

            window.confirmAffectedModal = function () {
                const form = pendingForm;
                if (!form) {
                    closeAffectedModal();
                    return;
                }

                if (affectedModal.dataset.needsConfirm === '1') {
                    const hidden = form.querySelector('input[name="confirm_affected"]');
                    if (hidden) hidden.value = '1';
                }

                pendingForm = null;
                affectedModal.classList.add('hidden');
                affectedModal.classList.remove('flex');
                document.body.style.overflow = '';

                HTMLFormElement.prototype.submit.call(form);
            };

            // ---------- create submit ----------
            createForm.addEventListener('submit', async function (event) {
                if (createForm.dataset.previewPassed === '1') {
                    createForm.dataset.previewPassed = '0';
                    return; // allow the real submission
                }

                event.preventDefault();
                createConfirmInput.value = '0';

                const windows = readCreateWindows();
                if (windows.length === 0) {
                    createForm.submit();
                    return;
                }

                setBusy(true);

                try {
                    const totals = await collectPreview(windows, null);
                    setBusy(false);

                    if (totals.occupying_count > 0 || totals.overlapping_count > 0) {
                        openAffectedModal(totals, createForm);
                        return;
                    }

                    // Nothing to confirm - real submit.
                    createForm.dataset.previewPassed = '1';
                    createForm.submit();
                } catch (error) {
                    setBusy(false);
                    alert('Could not check existing appointments. Please try again.');
                }
            });

            // ---------- edit modal ----------
            function toggleEditTimeFields() {
                const disabled = editAllDay.checked;
                editTimeFields.classList.toggle('hidden', disabled);
                document.getElementById('edit-start_time').disabled = disabled;
                document.getElementById('edit-end_time').disabled = disabled;
            }

            editAllDay.addEventListener('change', toggleEditTimeFields);

            window.openEditModal = function (button) {
                const data = button.dataset;

                editForm.action = data.url;
                document.getElementById('edit-start_date').value = data.startDate || '';
                document.getElementById('edit-end_date').value = data.endDate || '';
                editAllDay.checked = data.allDay === '1';
                document.getElementById('edit-start_time').value = data.startTime || '08:00';
                document.getElementById('edit-end_time').value = data.endTime || '08:30';
                document.getElementById('edit-reason').value = data.reason || '';
                editConfirmInput.value = '0';
                editForm.dataset.ignoreId = data.id || '';
                editForm.dataset.previewPassed = '0';

                toggleEditTimeFields();

                editModal.classList.remove('hidden');
                editModal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            };

            window.closeEditModal = function () {
                editModal.classList.add('hidden');
                editModal.classList.remove('flex');
                editForm.reset();
                editForm.dataset.ignoreId = '';
                editForm.dataset.previewPassed = '0';
                document.body.style.overflow = '';
            };

            editForm.addEventListener('submit', async function (event) {
                if (editForm.dataset.previewPassed === '1') {
                    editForm.dataset.previewPassed = '0';
                    return;
                }

                event.preventDefault();
                editConfirmInput.value = '0';

                const allDay = editAllDay.checked;
                const win = {
                    start_date: document.getElementById('edit-start_date').value,
                    end_date: document.getElementById('edit-end_date').value,
                    all_day: allDay ? '1' : '0',
                };

                if (!allDay) {
                    win.start_time = document.getElementById('edit-start_time').value;
                    win.end_time = document.getElementById('edit-end_time').value;
                }

                if (!win.start_date || !win.end_date) {
                    editForm.submit();
                    return;
                }

                setBusy(true);

                try {
                    const totals = await collectPreview(
                        [win],
                        editForm.dataset.ignoreId || null
                    );
                    setBusy(false);

                    if (totals.occupying_count > 0 || totals.overlapping_count > 0) {
                        openAffectedModal(totals, editForm);
                        return;
                    }

                    editForm.dataset.previewPassed = '1';
                    editForm.submit();
                } catch (error) {
                    setBusy(false);
                    alert('Could not check existing appointments. Please try again.');
                }
            });

            // ---------- remove modal ----------
            window.openRemoveModal = function (button) {
                document.getElementById('removeClosureForm').action = button.dataset.url;
                document.getElementById('removeMessage').textContent =
                    'Remove the closure for ' + (button.dataset.period || 'this period') +
                    '? The window reopens for new bookings.';

                removeModal.classList.remove('hidden');
                removeModal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            };

            window.closeRemoveModal = function () {
                removeModal.classList.add('hidden');
                removeModal.classList.remove('flex');
                document.getElementById('removeClosureForm').removeAttribute('action');
                document.body.style.overflow = '';
            };

            // ---------- shared modal plumbing ----------
            [editModal, removeModal, affectedModal].forEach(function (modal) {
                modal?.addEventListener('click', function (event) {
                    if (event.target !== this) return;
                    if (modal === editModal) closeEditModal();
                    else if (modal === removeModal) closeRemoveModal();
                    else closeAffectedModal();
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') return;

                if (!affectedModal.classList.contains('hidden')) {
                    closeAffectedModal();
                } else if (!editModal.classList.contains('hidden')) {
                    closeEditModal();
                } else if (!removeModal.classList.contains('hidden')) {
                    closeRemoveModal();
                }
            });
        })();
    </script>
</x-app-layout>
