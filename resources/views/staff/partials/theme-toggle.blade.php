<button type="button" class="operations-theme-toggle" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
    <span class="operations-theme-toggle-icon" aria-hidden="true">☾</span>
    <span class="operations-theme-toggle-label">Dark mode</span>
</button>
<script>
    (() => {
        const button = document.currentScript.previousElementSibling;
        const root = document.documentElement;
        const update = () => {
            const dark = root.dataset.operationsTheme === 'dark';
            button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('aria-pressed', String(dark));
            button.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
            button.querySelector('.operations-theme-toggle-icon').textContent = dark ? '☀' : '☾';
            button.querySelector('.operations-theme-toggle-label').textContent = dark ? 'Light mode' : 'Dark mode';
        };
        button.addEventListener('click', () => {
            const next = root.dataset.operationsTheme === 'dark' ? 'light' : 'dark';
            root.dataset.operationsTheme = next;
            try { localStorage.setItem('kkk-operations-mode', next); } catch (_) {}
            update();
        });
        update();
    })();
</script>
