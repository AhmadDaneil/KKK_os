<dialog id="logout-confirmation-dialog" class="logout-confirmation-dialog" aria-labelledby="logout-confirmation-title">
    <div class="logout-confirmation-icon" aria-hidden="true">?</div>
    <h2 id="logout-confirmation-title">Log out now?</h2>
    <p>Make sure you have saved your work before logging out.</p>
    <div class="logout-confirmation-actions">
        <button id="cancel-logout" type="button">No, stay here</button>
        <button id="confirm-logout" class="is-danger" type="button">Yes, log out</button>
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
            confirmButton.textContent = 'Logging out...';
            pendingLogoutForm.submit();
        });
    });
</script>
@include('partials.malay-validation')
