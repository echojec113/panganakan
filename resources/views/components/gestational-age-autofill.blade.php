<script>
    (function () {
        const field = document.getElementById('gestational_age');
        if (!field) {
            return;
        }

        const referenceDate = document.getElementById(field.dataset.gaReference);
        const hint = document.getElementById('ga_hint');
        const form = field.form;
        const oldInputPresent = field.dataset.gaOldInput === 'true';
        const initialExpected = field.dataset.gaInitialExpected;
        let lastAutoFilledValue = null;

        function getLmp() {
            const patientSelect = form.querySelector('select[name="patient_id"]');
            if (patientSelect) {
                return patientSelect.selectedOptions[0]?.dataset.lmp
                    || field.dataset.gaLmp
                    || '';
            }

            const lockedPatientInput = form.querySelector('input[name="patient_id"][data-lmp]');
            return lockedPatientInput?.dataset.lmp || field.dataset.gaLmp || '';
        }

        function setHint(message, className) {
            hint.textContent = message;
            hint.classList.remove('text-green-600', 'text-orange-600', 'text-red-600');
            if (className) {
                hint.classList.add(className);
            }
        }

        function calculateExpected(lmp, reference) {
            if (!lmp || !reference) {
                return { status: 'missing' };
            }

            const lmpDate = new Date(`${lmp.slice(0, 10)}T00:00:00Z`);
            const referenceValue = reference.slice(0, 10);
            const referenceDateValue = new Date(`${referenceValue}T00:00:00Z`);
            if (Number.isNaN(lmpDate.getTime()) || Number.isNaN(referenceDateValue.getTime())) {
                return { status: 'missing' };
            }

            const differenceDays = (referenceDateValue - lmpDate) / 86400000;
            if (differenceDays < 0) {
                return { status: 'reversed' };
            }

            const weeks = Math.round((differenceDays / 7) * 10) / 10;
            if (weeks < 4 || weeks > 42) {
                return { status: 'out-of-range', weeks };
            }

            return { status: 'valid', weeks };
        }

        function validateCurrentValue(expectedWeeks) {
            const enteredWeeks = Number.parseFloat(field.value);
            if (!Number.isFinite(enteredWeeks) || !Number.isFinite(expectedWeeks)) {
                field.classList.remove('border-red-500');
                field.parentElement.querySelector('.ga-validation-error')?.remove();
                return true;
            }

            if (Math.abs(expectedWeeks - enteredWeeks) > 3) {
                field.classList.add('border-red-500');
                let error = field.parentElement.querySelector('.ga-validation-error');
                if (!error) {
                    error = document.createElement('p');
                    error.className = 'ga-validation-error text-red-500 text-xs mt-1';
                    field.parentElement.appendChild(error);
                }
                error.textContent = `Entered GA must be within ±3 weeks of the expected ${expectedWeeks.toFixed(1)} weeks.`;
                return false;
            }

            field.classList.remove('border-red-500');
            field.parentElement.querySelector('.ga-validation-error')?.remove();
            return true;
        }

        function refresh(recalculate) {
            const lmp = getLmp();
            const reference = referenceDate?.value || '';

            if (!lmp) {
                const patientSelect = form.querySelector('select[name="patient_id"]');
                setHint(patientSelect && !patientSelect.value
                    ? 'Select a patient to calculate expected gestational age.'
                    : 'No LMP available for automatic GA calculation.');
                if (recalculate && lastAutoFilledValue === field.value) {
                    field.value = '';
                    lastAutoFilledValue = null;
                }
                field.classList.remove('border-red-500');
                field.parentElement.querySelector('.ga-validation-error')?.remove();
                return true;
            }

            const result = calculateExpected(lmp, reference);
            if (result.status === 'missing') {
                setHint('Enter a valid clinical date to calculate gestational age.', 'text-orange-600');
                if (recalculate && lastAutoFilledValue === field.value) {
                    field.value = '';
                    lastAutoFilledValue = null;
                }
                return true;
            }

            if (result.status === 'reversed') {
                setHint('Clinical date cannot be before the patient’s LMP.', 'text-red-600');
                if (recalculate && lastAutoFilledValue === field.value) {
                    field.value = '';
                    lastAutoFilledValue = null;
                }
                return false;
            }

            if (result.status === 'out-of-range') {
                setHint(`Calculated GA: ${result.weeks.toFixed(1)} weeks is outside the allowed 4–42 week range.`, 'text-red-600');
                if (recalculate && lastAutoFilledValue === field.value) {
                    field.value = '';
                    lastAutoFilledValue = null;
                }
                return false;
            }

            const expected = result.weeks.toFixed(1);
            setHint(`Expected GA: ${expected} weeks based on LMP`);

            if (recalculate) {
                field.value = expected;
                lastAutoFilledValue = expected;
            } else if (!oldInputPresent && initialExpected && field.value === initialExpected) {
                lastAutoFilledValue = initialExpected;
            }

            return validateCurrentValue(result.weeks);
        }

        referenceDate?.addEventListener('change', function () {
            refresh(true);
        });

        form.querySelector('select[name="patient_id"]')?.addEventListener('change', function () {
            refresh(true);
        });

        field.addEventListener('input', function () {
            refresh(false);
        });

        window.validateGestationalAge = function () {
            return refresh(false);
        };

        refresh(false);
    })();
</script>
