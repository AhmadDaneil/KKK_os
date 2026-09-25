<button class="customer-theme-toggle" type="button" aria-label="Tukar kepada mod gelap" aria-pressed="false" title="Tukar tema">
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
            button.setAttribute('aria-label', isDark ? 'Tukar kepada mod cerah' : 'Tukar kepada mod gelap');
            button.title = isDark ? 'Mod cerah' : 'Mod gelap';
        }

        applyTheme(savedTheme || (systemPrefersDark ? 'dark' : 'light'));

        button.addEventListener('click', function () {
            const theme = root.dataset.customerTheme === 'dark' ? 'light' : 'dark';
            localStorage.setItem(key, theme);
            applyTheme(theme);
        });
    }());
</script>
