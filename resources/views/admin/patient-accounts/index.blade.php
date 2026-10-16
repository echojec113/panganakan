<x-app-layout>
    <style>
        .patient-accounts-theme {
            --pa-primary: #55B85A;
            --pa-primary-hover: #4aa04c;
            --pa-soft: #F6F4EE;
            --pa-border: #E7E9E5;
            --pa-text: #19355F;
            --pa-muted: #657083;
        }

        .patient-accounts-theme .pa-card,
        .patient-accounts-theme .pa-modal,
        .patient-accounts-theme .pa-filter,
        .patient-accounts-theme .pa-table-head {
            border-color: var(--pa-border);
        }

        .patient-accounts-theme .pa-filter:focus,
        .patient-accounts-theme .pa-search:focus {
            border-color: var(--pa-primary);
            --tw-ring-color: var(--pa-primary);
        }

        .patient-accounts-theme .pa-table-head {
            background-color: var(--pa-soft);
        }

        .patient-accounts-theme .pa-row:hover {
            background-color: var(--pa-soft);
        }

        .patient-accounts-theme .pa-name { color: var(--pa-text); }
        .patient-accounts-theme .pa-muted { color: var(--pa-muted); }

        .patient-accounts-theme .pa-tab {
            border-color: var(--pa-border);
            color: var(--pa-muted);
            background-color: #fff;
        }

        .patient-accounts-theme .pa-tab:hover {
            background-color: var(--pa-soft);
            color: var(--pa-text);
        }

        .patient-accounts-theme .pa-tab.active {
            background-color: var(--pa-primary);
            border-color: var(--pa-primary);
            color: #fff;
        }

        .patient-accounts-theme .pa-tab .pa-tab-count {
            background-color: rgba(255, 255, 255, 0.22);
        }

        .patient-accounts-theme .pa-tab:not(.active) .pa-tab-count {
            background-color: var(--pa-soft);
            color: var(--pa-muted);
        }

        .patient-accounts-theme .pa-stat-value { color: var(--pa-text); }

        .patient-accounts-theme { container-type: inline-size; min-width: 0; max-width: 100%; overflow-wrap: anywhere; }
        .patient-accounts-theme * { box-sizing: border-box; }
        .patient-accounts-theme .flex > * { min-width: 0; }
        .patient-accounts-theme table { table-layout: fixed; }
        .patient-accounts-theme td { overflow-wrap: anywhere; }

        /* Email: prefer one complete line; break only when a short address
           genuinely cannot fit (no ellipsis, no hidden content, no eager
           character-by-character wrapping). */
        .patient-accounts-theme td.pa-email {
            overflow-wrap: break-word;
            word-break: normal;
            hyphens: none;
        }

        .patient-accounts-theme .pa-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
        .patient-accounts-theme form.pa-searchbar { flex-wrap: wrap; }
        .patient-accounts-theme form.pa-searchbar input[type="search"] { flex: 1 1 220px; min-width: 0; max-width: 100%; }
        .patient-accounts-theme nav, .patient-accounts-theme nav > .flex { min-width: 0; max-width: 100%; flex-wrap: wrap; gap: 12px; }

        #accountDetailsModal, #accountActionModal { overflow-y: auto; }
        #accountDetailsModal .pa-modal, #accountActionModal .pa-modal {
            margin-inline: 0;
            min-width: 0;
            max-height: calc(100dvh - 3rem);
            overflow-y: auto;
            overflow-wrap: anywhere;
        }
        .patient-accounts-theme .pa-modal button { min-height: 44px; white-space: normal; }
        .patient-accounts-theme .pa-detail-label { color: var(--pa-muted); }

        @container (max-width: 860px) {
            .patient-accounts-theme table,
            .patient-accounts-theme tbody,
            .patient-accounts-theme tr { display: block; width: 100%; }
            .patient-accounts-theme thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
            .patient-accounts-theme .pa-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px 16px;
                padding: 16px;
            }
            .patient-accounts-theme .pa-row td { display: block; min-width: 0; padding: 0; text-align: left; }
            .patient-accounts-theme .pa-row td::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 6px;
                font-size: 12px;
                font-weight: 600;
                color: var(--pa-muted);
            }
            .patient-accounts-theme .pa-row td:last-child { grid-column: 1 / -1; }
            .patient-accounts-theme .pa-row td:last-child .pa-actions { justify-content: flex-start; flex-wrap: wrap; }
            .patient-accounts-theme .pa-row td:last-child .pa-actions > * { min-height: 44px; }
        }

        @media (max-width: 639px) {
            .patient-accounts-theme .pa-stats { grid-template-columns: minmax(0, 1fr); }
            .patient-accounts-theme .pa-modal { padding: 20px; }
            .patient-accounts-theme .pa-modal-actions { flex-direction: column; }
            .patient-accounts-theme .pa-modal-actions > * { width: 100%; }
        }
    </style>

    <div class="patient-accounts-theme min-h-screen bg-[#FCFBF8] p-4 md:p-8">
        <div class="mx-auto max-w-7xl">

            {{-- HEADER --}}
            <x-app-header
                title="Patient Account Management"
                subtitle="Manage Patient Portal access and account setup"
                class="mb-6"
            />

            {{-- FLASH --}}
            <x-flash type="success" :message="session('success')" class="mb-6" />
            <x-flash type="error" :message="session('error')" class="mb-6" />
            <x-flash type="warning" :message="session('warning')" class="mb-6" />

            {{-- SUMMARY CARDS (real database counts, independent of the search) --}}
            <div class="pa-stats mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white px-3 py-4 shadow-sm xl:px-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Accounts</p>
                            <p class="pa-stat-value mt-2 text-2xl font-bold" data-stat="total">{{ $summaryCounts['total'] }}</p>
                            <p class="mt-1 text-xs text-gray-400">Patient Portal login accounts</p>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-[#19355F]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-3 py-4 shadow-sm xl:px-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active</p>
                            <p class="pa-stat-value mt-2 text-2xl font-bold" data-stat="active">{{ $summaryCounts['active'] }}</p>
                            <p class="mt-1 text-xs text-gray-400">Setup complete, can sign in</p>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white px-3 py-4 shadow-sm xl:px-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pending Setup</p>
                            <p class="pa-stat-value mt-2 text-2xl font-bold" data-stat="pending">{{ $summaryCounts['pending'] }}</p>
                            <p class="mt-1 text-xs text-gray-400">Awaiting the patient's password setup</p>
                        </div>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STATUS FILTERS --}}
            <div class="mb-4">
                <div class="pa-tabs" role="group" aria-label="Account status filter">
                    @foreach ([
                        'all' => ['All', $summaryCounts['total']],
                        'active' => ['Active', $summaryCounts['active']],
                        'pending' => ['Pending Setup', $summaryCounts['pending']],
                        'deactivated' => ['Deactivated', $summaryCounts['deactivated']],
                    ] as $statusKey => $tab)
                        <a href="{{ route('patient-accounts.index', array_filter(['status' => $statusKey === 'all' ? null : $statusKey, 'search' => $search !== '' ? $search : null], fn ($value) => $value !== null)) }}"
                           class="pa-tab inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition {{ $status === $statusKey ? 'active' : '' }}"
                           @if ($status === $statusKey) aria-current="page" @endif>
                            {{ $tab[0] }}
                            @if ($tab[1] !== null)
                                <span class="pa-tab-count inline-flex min-w-[1.5rem] justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold">{{ $tab[1] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- SEARCH --}}
            <form id="patientAccountSearchForm" method="GET" action="{{ route('patient-accounts.index') }}"
                  class="pa-searchbar mb-6 flex flex-col gap-3 rounded-2xl border bg-white p-4 shadow-sm md:flex-row md:items-center">
                <input type="hidden" name="status" value="{{ $status }}">

                <div class="relative w-full min-w-0 flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none"
                         stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>

                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Search by patient name or registered email..."
                        class="pa-search w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-10 text-sm text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-[#55B85A] focus:ring-2 focus:ring-[#55B85A]/30"
                    >

                    @if ($search !== '')
                        <a href="{{ route('patient-accounts.index', array_filter(['status' => $status === 'all' ? null : $status], fn ($value) => $value !== null)) }}"
                           class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30"
                           title="Clear search" aria-label="Clear account search">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="shrink-0 whitespace-nowrap text-sm text-gray-400">
                    <span class="font-medium text-gray-600">{{ number_format($accounts->total()) }}</span>
                    {{ Str::plural('account', $accounts->total()) }} found
                </div>

                <button type="submit"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#55B85A] px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:ring-offset-2">
                    Search
                </button>
            </form>

            @error('search')
                <p class="mb-4 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('status')
                <p class="mb-4 text-sm text-red-600">{{ $message }}</p>
            @enderror

            {{-- ACCOUNTS TABLE --}}
            <div class="pa-card overflow-hidden rounded-2xl border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <colgroup>
                            {{-- Name / Email / Status / Created / Links / Actions.
                                 Email gets the largest share so complete
                                 addresses stay on one line where space allows;
                                 Status is kept narrow for a single-line badge. --}}
                            <col style="width: 18%">
                            <col style="width: 26%">
                            <col style="width: 15%">
                            <col style="width: 16%">
                            <col style="width: 12%">
                            <col style="width: 13%">
                        </colgroup>

                        <thead class="pa-table-head border-b">
                            <tr>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Patient Name</th>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Email</th>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Account Created</th>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Linked Pregnancies</th>
                                <th class="px-4 md:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                            @forelse($accounts as $account)
                                @php
                                    $isDeactivated = $account->trashed();
                                    $isPending = ! $isDeactivated && (bool) $account->must_change_password;

                                    $statusLabel = $isDeactivated ? 'Deactivated' : ($isPending ? 'Pending Setup' : 'Active');
                                    $statusVariant = $isDeactivated ? 'danger' : ($isPending ? 'warning' : 'success');

                                    $linkedPatient = $account->patientAccount;
                                    $resendUrl = $linkedPatient
                                        ? route('patients.account.resend-setup-email', $linkedPatient->id)
                                        : null;
                                @endphp

                                <tr class="pa-row border-b transition">
                                    <td data-label="Patient Name" class="pa-name px-4 md:px-6 py-4 text-center font-medium align-middle">
                                        {{ $account->name }}
                                    </td>

                                    <td data-label="Email" class="pa-email pa-muted px-4 md:px-6 py-4 text-center align-middle">
                                        {{ $account->email }}
                                    </td>

                                    <td data-label="Status" data-status="{{ $isDeactivated ? 'deactivated' : ($isPending ? 'pending-setup' : 'active') }}" class="px-4 md:px-6 py-4 text-center align-middle whitespace-nowrap">
                                        <x-status-badge :variant="$statusVariant">{{ $statusLabel }}</x-status-badge>
                                    </td>

                                    <td data-label="Account Created" class="pa-muted px-4 md:px-6 py-4 text-center align-middle">
                                        {{ $account->created_at->format('M d, Y h:i A') }}
                                    </td>

                                    <td data-label="Linked Pregnancies" data-links="{{ $account->pregnancy_links_count }}" class="pa-muted px-4 md:px-6 py-4 text-center align-middle">
                                        {{ $account->pregnancy_links_count }}
                                    </td>

                                    <td data-label="Actions" class="px-4 md:px-6 py-4 text-center align-middle">
                                        <div class="pa-actions flex justify-center gap-1">
                                            <button
                                                type="button"
                                                onclick="openAccountDetails(this)"
                                                title="View Account Details"
                                                aria-label="View account details for {{ $account->name }}"
                                                data-details-name="{{ $account->name }}"
                                                data-details-email="{{ $account->email }}"
                                                data-details-status="{{ $statusLabel }}"
                                                data-details-setup="{{ $isDeactivated ? 'Not applicable (account deactivated)' : ($isPending ? 'Pending - patient has not chosen a password yet' : 'Completed - patient chose their own password') }}"
                                                data-details-created="{{ $account->created_at->format('M d, Y h:i A') }}"
                                                data-details-links="{{ $account->pregnancy_links_count }}"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#19355F] transition-all duration-150 hover:bg-[#EDF5EC] hover:text-[#367E4B]">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"></path>
                                                </svg>
                                            </button>

                                            @if ($isPending && $resendUrl)
                                                {{-- Reuses the existing Phase 1.5 endpoint: one setup token
                                                     system, 24-hour single-use tokens, 60-second throttle,
                                                     existing audit logging - all unchanged. --}}
                                                <button
                                                    type="button"
                                                    onclick="openAccountAction(this)"
                                                    title="Resend Setup Email"
                                                    aria-label="Resend setup email for {{ $account->name }}"
                                                    data-action-mode="form"
                                                    data-action-url="{{ $resendUrl }}"
                                                    data-action-title="Resend Setup Email?"
                                                    data-action-message="Send a new setup email to {{ $account->name }} at {{ $account->email }}? The previous setup link will stop working. The patient will choose their own password using the new link (valid for 24 hours, single use)."
                                                    data-action-submit="Resend Setup Email"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-amber-600 transition-all duration-150 hover:bg-amber-50 hover:text-amber-800">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"></path>
                                                    </svg>
                                                </button>
                                            @endif

                                            @if (! $isDeactivated)
                                                {{-- Sprint 2: SoftDeletes deactivation. Available for Active AND
                                                     Pending Setup accounts; the pending flag is never modified, so
                                                     a reactivated pending account returns to Pending Setup. --}}
                                                <button
                                                    type="button"
                                                    onclick="openAccountAction(this)"
                                                    title="Deactivate Account"
                                                    aria-label="Deactivate account for {{ $account->name }}"
                                                    data-action-mode="form"
                                                    data-action-url="{{ route('patient-accounts.deactivate', array_merge(['account' => $account], request()->query())) }}"
                                                    data-action-title="Deactivate Patient Account?"
                                                    data-action-message="Deactivate {{ $account->name }}? The patient will be signed out and will not be able to sign in until an admin reactivates the account. Linked pregnancies and clinical records are not deleted."
                                                    data-action-submit="Deactivate"
                                                    data-action-variant="danger"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#B42318] transition-all duration-150 hover:bg-red-50 hover:text-[#912018]">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.477 18.75H20.25a2.25 2.25 0 0 0 2.25-2.25v-6.75M13.477 18.75 9.75 15.027m3.727 3.723V9.973L9.75 6.25m-3.75 3.723a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25h6.75"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 3.75 16.5 16.5"></path>
                                                    </svg>
                                                </button>
                                            @else
                                                {{-- Sprint 2: SoftDeletes restore. Offered only for deactivated
                                                     rows, right next to nothing else - the archive stays
                                                     discoverable through the Deactivated filter. --}}
                                                <button
                                                    type="button"
                                                    onclick="openAccountAction(this)"
                                                    title="Reactivate Account"
                                                    aria-label="Reactivate account for {{ $account->name }}"
                                                    data-action-mode="form"
                                                    data-action-url="{{ route('patient-accounts.reactivate', array_merge(['account' => $account], request()->query())) }}"
                                                    data-action-title="Reactivate Patient Account?"
                                                    data-action-message="Reactivate {{ $account->name }}? The patient will be able to sign in again using their existing password (or complete a pending setup). No linked pregnancy or clinical record was ever deleted."
                                                    data-action-submit="Reactivate"
                                                    data-action-variant="success"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#367E4B] transition-all duration-150 hover:bg-[#EDF5EC] hover:text-[#2A5F39]">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="pa-muted py-10 text-center">
                                        No patient accounts found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PAGINATION (search + status preserved via withQueryString) --}}
            <div class="mt-6">
                {{ $accounts->links() }}
            </div>

        </div>
    </div>

    {{-- ===================== ACCOUNT DETAILS MODAL ===================== --}}
    <div id="accountDetailsModal" role="dialog" aria-modal="true" aria-labelledby="accountDetailsTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="pa-modal mx-4 w-full max-w-lg rounded-xl border bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="accountDetailsTitle" class="pa-name text-base font-semibold">Account Details</h2>
                    <p class="pa-muted mt-1 text-sm leading-6">
                        Access information for this Patient Portal login.
                    </p>
                </div>
                <button type="button" onclick="closeAccountDetails()"
                        aria-label="Close account details"
                        class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Patient Name</dt>
                    <dd id="detailName" class="pa-name min-w-0 break-all font-semibold text-right"></dd>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Registered Email</dt>
                    <dd id="detailEmail" class="pa-name min-w-0 break-all font-semibold text-right"></dd>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Account Status</dt>
                    <dd id="detailStatus" class="pa-name font-semibold text-right"></dd>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Setup Status</dt>
                    <dd id="detailSetup" class="pa-name min-w-0 font-semibold text-right"></dd>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Account Created</dt>
                    <dd id="detailCreated" class="pa-name font-semibold text-right"></dd>
                </div>
                <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg bg-[#F6F4EE] px-3 py-2">
                    <dt class="pa-detail-label font-medium">Linked Pregnancies</dt>
                    <dd id="detailLinks" class="pa-name font-semibold text-right"></dd>
                </div>
            </dl>

            <p class="pa-muted mt-4 text-xs leading-5">
                Passwords and clinical records are never displayed here.
            </p>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeAccountDetails()"
                        class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ===================== ACTION CONFIRM MODAL ===================== --}}
    <div id="accountActionModal" role="dialog" aria-modal="true" aria-labelledby="accountActionTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="pa-modal mx-4 w-full max-w-md rounded-xl border bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div id="accountActionIcon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#EDF5EC] text-[#367E4B]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"></path>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 id="accountActionTitle" class="pa-name text-base font-semibold"></h2>
                    <p id="accountActionMessage" class="pa-muted mt-1 text-sm leading-6"></p>
                </div>
            </div>

            <div class="pa-modal-actions mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeAccountAction()"
                        class="rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Cancel
                </button>
                <form id="accountActionForm" method="POST" class="inline">
                    @csrf
                    <button type="submit" id="accountActionButton" disabled
                            class="inline-flex items-center justify-center rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] disabled:cursor-not-allowed disabled:opacity-60">
                        Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const accountDetailsModal = document.getElementById('accountDetailsModal');
        const accountActionModal = document.getElementById('accountActionModal');
        const accountActionForm = document.getElementById('accountActionForm');
        const accountActionButton = document.getElementById('accountActionButton');
        const accountActionIcon = document.getElementById('accountActionIcon');

        // Sprint 2: the shared confirm modal recolors itself per action so a
        // destructive Deactivate is visually distinct from the green
        // resend/reset/reactivate confirmations. Unknown variants fall back
        // to success, the Sprint 1 look.
        const accountActionVariants = {
            success: { icon: 'bg-[#EDF5EC] text-[#367E4B]', button: 'bg-[#55B85A] hover:bg-[#4aa04c]' },
            danger: { icon: 'bg-[#FDECEC] text-[#B42318]', button: 'bg-[#B42318] hover:bg-[#912018]' },
        };
        const accountActionButtonBase =
            'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60';

        function applyAccountActionVariant(variant) {
            const preset = accountActionVariants[variant] || accountActionVariants.success;

            accountActionIcon.className =
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full ' + preset.icon;
            accountActionButton.className = accountActionButtonBase + ' ' + preset.button;
        }

        applyAccountActionVariant('success');

        function openAccountDetails(button) {
            document.getElementById('detailName').textContent = button.dataset.detailsName || '';
            document.getElementById('detailEmail').textContent = button.dataset.detailsEmail || '';
            document.getElementById('detailStatus').textContent = button.dataset.detailsStatus || '';
            document.getElementById('detailSetup').textContent = button.dataset.detailsSetup || '';
            document.getElementById('detailCreated').textContent = button.dataset.detailsCreated || '';
            document.getElementById('detailLinks').textContent = button.dataset.detailsLinks || '';

            accountDetailsModal.classList.remove('hidden');
            accountDetailsModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeAccountDetails() {
            accountDetailsModal.classList.add('hidden');
            accountDetailsModal.classList.remove('flex');
            ['detailName', 'detailEmail', 'detailStatus', 'detailSetup', 'detailCreated', 'detailLinks']
                .forEach(function (id) { document.getElementById(id).textContent = ''; });
            document.body.style.overflow = '';
        }

        function openAccountAction(button) {
            const actionUrl = button.dataset.actionUrl;
            if (!actionUrl) return;

            accountActionForm.action = actionUrl;
            accountActionButton.disabled = false;
            applyAccountActionVariant(button.dataset.actionVariant || 'success');
            document.getElementById('accountActionTitle').textContent = button.dataset.actionTitle || '';
            document.getElementById('accountActionMessage').textContent = button.dataset.actionMessage || '';
            accountActionButton.textContent = button.dataset.actionSubmit || 'Confirm';

            accountActionModal.classList.remove('hidden');
            accountActionModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeAccountAction() {
            accountActionModal.classList.add('hidden');
            accountActionModal.classList.remove('flex');
            accountActionForm.removeAttribute('action');
            accountActionButton.disabled = true;
            accountActionButton.textContent = 'Confirm';
            applyAccountActionVariant('success');
            document.getElementById('accountActionTitle').textContent = '';
            document.getElementById('accountActionMessage').textContent = '';
            document.body.style.overflow = '';
        }

        [accountDetailsModal, accountActionModal].forEach(function (modal) {
            modal?.addEventListener('click', function (event) {
                if (event.target !== this) return;
                modal === accountDetailsModal ? closeAccountDetails() : closeAccountAction();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;

            if (!accountActionModal.classList.contains('hidden')) {
                closeAccountAction();
            } else if (!accountDetailsModal.classList.contains('hidden')) {
                closeAccountDetails();
            }
        });
    </script>
</x-app-layout>
