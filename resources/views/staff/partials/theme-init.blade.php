<script>
    (() => {
        const key = 'kkk-operations-mode';
        let saved = null;
        try { saved = localStorage.getItem(key); } catch (_) {}
        const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.operationsTheme = saved === 'dark' || saved === 'light' ? saved : preferred;
    })();
</script>
