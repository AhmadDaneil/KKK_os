<dialog id="logout-confirmation-dialog" class="logout-confirmation-dialog" aria-labelledby="logout-confirmation-title">
    <div class="logout-confirmation-icon" aria-hidden="true">?</div>
    <h2 id="logout-confirmation-title">Log keluar sekarang?</h2>
    <p>Pastikan semua kerja yang sedang dibuat telah disimpan sebelum anda keluar.</p>
    <div class="logout-confirmation-actions">
        <button id="cancel-logout" type="button">Tidak, kekal</button>
        <button id="confirm-logout" class="is-danger" type="button">Ya, log keluar</button>
    </div>
</dialog>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const logoutForms = document.querySelectorAll('.js-logout-form');
        const dialog = document.getElementById('logout-confirmation-dialog');
        const confirmButton = document.getElementById('confirm-logout');
        const cancelButton = document.getElementById('cancel-logout');
        let pendingLogoutForm = null;

        if (!logoutForms.length || !dialog || !confirmButton || !cancelButton) {
            return;
        }

        logoutForms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                pendingLogoutForm = form;
                dialog.showModal();
            });
        });

        cancelButton.addEventListener('click', function () {
            pendingLogoutForm = null;
            dialog.close();
        });

        confirmButton.addEventListener('click', function () {
            if (!pendingLogoutForm) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.textContent = 'Sedang log keluar...';
            pendingLogoutForm.submit();
        });
    });
</script>
