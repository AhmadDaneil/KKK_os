<script>
    (() => {
        const requiredMessage = 'Please fill out this field.';

        document.addEventListener('invalid', (event) => {
            const field = event.target;

            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                if (field.validity.valueMissing) {
                    field.setCustomValidity(requiredMessage);
                } else if (field.validity.typeMismatch && field.type === 'email') {
                    field.setCustomValidity('Please enter a valid email address.');
                } else if (field.validity.typeMismatch) {
                    field.setCustomValidity('Please enter a valid format.');
                } else if (field.validity.tooShort) {
                    field.setCustomValidity(`Please enter at least ${field.minLength} characters.`);
                } else if (field.validity.tooLong) {
                    field.setCustomValidity(`Please enter no more than ${field.maxLength} characters.`);
                } else if (field.validity.rangeUnderflow || field.validity.rangeOverflow) {
                    field.setCustomValidity('Please enter a value within the valid range.');
                } else if (field.validity.patternMismatch) {
                    field.setCustomValidity(field.type === 'tel'
                        ? 'Please enter a 10- or 11-digit phone number starting with 01.'
                        : 'Please enter a valid format.');
                } else if (field.validity.badInput || field.validity.stepMismatch) {
                    field.setCustomValidity('Please enter a valid value.');
                }
            }
        }, true);

        const clearCustomMessage = (event) => {
            const field = event.target;

            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                field.setCustomValidity('');
            }
        };

        document.addEventListener('input', clearCustomMessage, true);
        document.addEventListener('change', clearCustomMessage, true);
    })();
</script>
