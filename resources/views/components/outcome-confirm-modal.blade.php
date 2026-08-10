{{--
    Reusable confirmation modal for Pregnancy Outcome follow-up actions
    (Confirm Still Pregnant / Record Unable to Contact).

    A single modal instance is shared by every trigger-button on the page.
    Trigger buttons must declare:
      data-outcome-confirm-trigger
      data-outcome-title
      data-outcome-message
      data-outcome-confirm-label
      data-outcome-action   (the staff-only POST route)
      data-outcome-patient  (inline text, e.g. "Maria Reyes" — never JSON)
      data-outcome-tone     (optional: "confirm" or "alert")

    Opening the modal never submits anything; only the Confirm button inside
    the modal performs the POST. Backend stays authoritative — this script is
    a UX layer only and never re-validates eligibility.
--}}
<div id="outcomeConfirmModal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4"
     role="dialog"
     aria-modal="true"
     aria-labelledby="outcomeConfirmModalTitle"
     aria-describedby="outcomeConfirmModalMessage"
     tabindex="-1">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="border-b border-gray-100 px-6 py-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                    <svg id="outcomeConfirmIcon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path id="outcomeConfirmIconPath" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 15V7m8 4v8m-8 4H4a2 2 0 01-2-2v-4a2 2 0 012-2h4m10 0h4a2 2 0 012 2v4a2 2 0 01-2 2h-4m-6-8h6m-6 4h6"></path>
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 id="outcomeConfirmModalTitle" class="text-lg font-bold text-gray-900"></h3>
                    <p id="outcomeConfirmPatient" class="mt-1 truncate text-sm font-semibold text-indigo-700"></p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5">
            <p id="outcomeConfirmModalMessage" class="text-sm leading-relaxed text-gray-700"></p>

            <form method="POST" id="outcomeConfirmForm" action="" class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 pt-5">
                @csrf
                <button type="button" id="outcomeConfirmCancel"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Cancel
                </button>
                <button type="submit" id="outcomeConfirmSubmit"
                        class="ml-auto inline-flex min-w-[10rem] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg id="outcomeConfirmSpinner" class="hidden h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span id="outcomeConfirmSubmitLabel">Confirm</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('outcomeConfirmModal');
        if (!modal || modal.hasAttribute('data-outcome-modal-initialised')) {
            return;
        }
        modal.setAttribute('data-outcome-modal-initialised', '1');

        var titleEl = document.getElementById('outcomeConfirmModalTitle');
        var patientEl = document.getElementById('outcomeConfirmPatient');
        var messageEl = document.getElementById('outcomeConfirmModalMessage');
        var form = document.getElementById('outcomeConfirmForm');
        var submitBtn = document.getElementById('outcomeConfirmSubmit');
        var submitLabelEl = document.getElementById('outcomeConfirmSubmitLabel');
        var spinnerEl = document.getElementById('outcomeConfirmSpinner');
        var cancelBtn = document.getElementById('outcomeConfirmCancel');
        var iconEl = document.getElementById('outcomeConfirmIcon');
        var iconPathEl = document.getElementById('outcomeConfirmIconPath');

        var lastTrigger = null;
        var originalLabel = '';
        var activeFormAction = '';
        var focusableSelector = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

        function isOpen() {
            return !modal.classList.contains('hidden');
        }

        function setOpen(open) {
            modal.classList.toggle('hidden', !open);
            modal.classList.toggle('flex', open);
        }

        function setBusy(busy) {
            submitBtn.disabled = busy;
            if (busy) {
                submitBtn.setAttribute('aria-busy', 'true');
                spinnerEl.classList.remove('hidden');
                submitLabelEl.textContent = 'Saving...';
            } else {
                submitBtn.removeAttribute('aria-busy');
                spinnerEl.classList.add('hidden');
                submitLabelEl.textContent = originalLabel || 'Confirm';
            }
        }

        function applyTone(tone) {
            var isAlert = tone === 'alert';
            iconEl.classList.toggle('bg-green-100', !isAlert);
            iconEl.classList.toggle('text-green-600', !isAlert);
            iconEl.classList.toggle('bg-rose-100', isAlert);
            iconEl.classList.toggle('text-rose-600', isAlert);
            iconPathEl.setAttribute('d', isAlert
                ? 'M8.7 15.3a4.5 4.5 0 006.6 0M8.5 10.5h.01M15.5 10.5h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z');
        }

        function close() {
            setOpen(false);
            setBusy(false);
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }
            lastTrigger = null;
        }

        function open(trigger) {
            titleEl.textContent = trigger.getAttribute('data-outcome-title') || 'Confirm Action';
            messageEl.textContent = trigger.getAttribute('data-outcome-message') || '';
            patientEl.textContent = trigger.getAttribute('data-outcome-patient') || '';
            activeFormAction = trigger.getAttribute('data-outcome-action') || '';
            originalLabel = trigger.getAttribute('data-outcome-confirm-label') || 'Confirm';
            lastTrigger = trigger;

            applyTone(trigger.getAttribute('data-outcome-tone') || 'confirm');

            form.action = activeFormAction;
            setBusy(false);
            setOpen(true);

            // Move focus into the dialog so keyboard and screen-reader users
            // can immediately operate the confirmation workflow.
            modal.focus({ preventScroll: true });
        }

        function trapFocus(event) {
            if (!isOpen()) {
                return;
            }
            var focusables = modal.querySelectorAll(focusableSelector);
            if (focusables.length === 0) {
                event.preventDefault();
                modal.focus({ preventScroll: true });
                return;
            }
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            } else if (!modal.contains(document.activeElement)) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('click', function (event) {
            if (!(event.target instanceof Element)) {
                return;
            }

            var trigger = event.target.closest('[data-outcome-confirm-trigger]');
            if (trigger) {
                event.preventDefault();
                open(trigger);
                return;
            }

            // Backdrop click closes only an open modal; clicks inside the
            // dialog (including the panel) never close it.
            if (isOpen() && event.target === modal) {
                close();
            }
        });

        cancelBtn.addEventListener('click', close);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (isOpen()) {
                    close();
                }
                return;
            }
            if (event.key === 'Tab') {
                trapFocus(event);
            }
        });

        // Duplicate-submission prevention: once submitted, the confirm button
        // is disabled and flips to the busy state until the page navigates.
        form.addEventListener('submit', function (event) {
            if (submitBtn.disabled) {
                event.preventDefault();
                return;
            }
            if (!activeFormAction) {
                event.preventDefault();
                return;
            }
            setBusy(true);
        });
    })();
</script>