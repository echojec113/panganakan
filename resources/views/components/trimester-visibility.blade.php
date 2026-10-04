{{--
    Trimester-based FIELD VISIBILITY (display only).

    Reads the EXISTING Gestational Age value (#gestational_age) and toggles a
    CSS class on wrappers marked with data-trimester-visible:

        data-trimester-visible="2,3"  visible from the 2nd trimester onward
        data-trimester-visible="3"    visible from the 3rd trimester only

    Classification (decimal weeks, never rounded):

        1.0 <= GA < 13.0  -> 1st Trimester
        13.0 <= GA < 28.0 -> 2nd Trimester
        GA >= 28.0        -> 3rd Trimester
        GA blank / invalid / < 1.0 -> not classified

    Safety contract:
    - Only the wrapper's CSS class changes. The input/select itself is never
      removed from the DOM, never disabled, never cleared and keeps its name,
      so it still submits its value (Edit must not erase historical data).
    - No second gestational-age calculation is performed: this script only
      reads the value produced by the existing GA autofill/validation.
    - No clinical, validation, database or risk logic is touched here.
--}}
<style>
    .trimester-hidden {
        display: none !important;
    }
</style>
<script>
    (function () {
        const gaField = document.getElementById('gestational_age');
        if (!gaField) {
            return;
        }

        const form = gaField.form;
        const controlledWrappers = Array.from(
            (form || document).querySelectorAll('[data-trimester-visible]')
        );
        const indicator = document.getElementById('trimester_indicator');

        const TRIMESTER_LABELS = {
            1: '1st Trimester',
            2: '2nd Trimester',
            3: '3rd Trimester',
        };

        /**
         * Single source of truth for trimester classification.
         * Reads the value as entered: no rounding, no re-derivation from LMP.
         */
        function classifyTrimester(rawValue) {
            const weeks = Number.parseFloat(rawValue);

            if (!Number.isFinite(weeks) || weeks < 1.0) {
                return null;
            }

            if (weeks < 13.0) {
                return 1;
            }

            if (weeks < 28.0) {
                return 2;
            }

            return 3;
        }

        /** Centralised visibility update for every controlled wrapper. */
        function updateTrimesterVisibility() {
            const trimester = classifyTrimester(gaField.value);

            controlledWrappers.forEach(function (wrapper) {
                const allowed = (wrapper.getAttribute('data-trimester-visible') || '')
                    .split(',')
                    .map(function (token) { return parseInt(token, 10); })
                    .filter(function (value) { return Number.isInteger(value); });

                const visible = trimester !== null && allowed.indexOf(trimester) !== -1;

                // Visual only: the field stays in the DOM, enabled, and keeps
                // its current value so submission and Edit preservation are
                // completely unaffected.
                wrapper.classList.toggle('trimester-hidden', !visible);
            });

            if (indicator) {
                // Display only: the "Trimester" label lives in the markup, so
                // this node carries the value alone. It has no `name` and is
                // never submitted to the backend.
                indicator.textContent = trimester === null
                    ? '—'
                    : TRIMESTER_LABELS[trimester];
            }
        }

        // Direct GA edits.
        gaField.addEventListener('input', updateTrimesterVisibility);
        gaField.addEventListener('change', updateTrimesterVisibility);

        // The existing GA autofill writes the value programmatically when the
        // visit date or patient changes; defer so that write has happened.
        const referenceDate = document.getElementById(gaField.dataset.gaReference || '');
        if (referenceDate) {
            referenceDate.addEventListener('change', function () {
                setTimeout(updateTrimesterVisibility, 0);
            });
        }

        const patientSelect = form ? form.querySelector('select[name="patient_id"]') : null;
        if (patientSelect) {
            patientSelect.addEventListener('change', function () {
                setTimeout(updateTrimesterVisibility, 0);
            });
        }

        // Correct visibility immediately on page load, before any user input.
        updateTrimesterVisibility();

        window.classifyPrenatalTrimester = classifyTrimester;
        window.updatePrenatalTrimesterVisibility = updateTrimesterVisibility;
    })();
</script>
