<button class="customer-theme-toggle" type="button" aria-label="Switch to dark mode" aria-pressed="false" title="Change theme">
    <span class="customer-theme-toggle-sun" aria-hidden="true">☀</span>
    <span class="customer-theme-toggle-moon" aria-hidden="true">☾</span>
</button>
<script>
    (function () {
        const key = 'kkk-customer-theme';
        const root = document.documentElement;
        const button = document.querySelector('.customer-theme-toggle');
        const savedTheme = localStorage.getItem(key);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        function applyTheme(theme) {
            const isDark = theme === 'dark';
            root.dataset.customerTheme = theme;
            button.setAttribute('aria-pressed', String(isDark));
            button.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            button.title = isDark ? 'Light mode' : 'Dark mode';
        }

        applyTheme(savedTheme || (systemPrefersDark ? 'dark' : 'light'));

        button.addEventListener('click', function () {
            const theme = root.dataset.customerTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem(key, theme);
            applyTheme(theme);
        });
    }());
</script>
