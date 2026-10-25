@include('patients.partials.address-autocomplete')
<script>
(function () {
    const section = document.querySelector('[data-staff-address]');
    if (!section) return;
    const form = section.closest('form');
    const fields = section.querySelector('fieldset');
    const toggle = section.querySelector('[name="replace_address"]');
    const inputs = Array.from(fields.querySelectorAll('input'));

    function validate(input) {
        const error = document.getElementById(input.getAttribute('aria-describedby')).querySelector('[data-address-error]');
        const empty = !fields.disabled && input.value.trim() === '';
        error.textContent = empty ? input.dataset.addressLabel + ' is required.' : '';
        error.classList.toggle('hidden', !empty);
        input.classList.toggle('border-red-500', empty);
        input.setAttribute('aria-invalid', empty ? 'true' : 'false');
        return !empty;
    }

    if (toggle) toggle.addEventListener('change', function () {
        fields.disabled = !toggle.checked;
        if (toggle.checked) inputs[0].focus();
    });
    inputs.forEach(function (input) {
        input.addEventListener('blur', function () { validate(input); });
        input.addEventListener('invalid', function () { validate(input); });
    });
    // Capture runs before either existing confirmation handler. Native form.submit()
    // remains unchanged; server validation is authoritative on every submission.
    form.addEventListener('submit', function (event) {
        const valid = inputs.map(validate).every(Boolean);
        if (!valid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            inputs.find(input => input.value.trim() === '').focus();
        }
    }, true);
})();
</script>
