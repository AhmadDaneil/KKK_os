<script>
    (() => {
        const requiredMessage = 'Sila isi ruangan ini.';

        document.addEventListener('invalid', (event) => {
            const field = event.target;

            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                if (field.validity.valueMissing) {
                    field.setCustomValidity(requiredMessage);
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
