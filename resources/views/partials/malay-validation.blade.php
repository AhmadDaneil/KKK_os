<script>
    (() => {
        const requiredMessage = 'Sila isi ruangan ini.';

        document.addEventListener('invalid', (event) => {
            const field = event.target;

            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                if (field.validity.valueMissing) {
                    field.setCustomValidity(requiredMessage);
                } else if (field.validity.typeMismatch && field.type === 'email') {
                    field.setCustomValidity('Sila masukkan alamat e-mel yang sah.');
                } else if (field.validity.typeMismatch) {
                    field.setCustomValidity('Sila masukkan format yang sah.');
                } else if (field.validity.tooShort) {
                    field.setCustomValidity(`Sila masukkan sekurang-kurangnya ${field.minLength} aksara.`);
                } else if (field.validity.tooLong) {
                    field.setCustomValidity(`Sila masukkan tidak lebih daripada ${field.maxLength} aksara.`);
                } else if (field.validity.rangeUnderflow || field.validity.rangeOverflow) {
                    field.setCustomValidity('Sila masukkan nilai dalam julat yang sah.');
                } else if (field.validity.badInput || field.validity.stepMismatch) {
                    field.setCustomValidity('Sila masukkan nilai yang sah.');
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
