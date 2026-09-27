<script>
(() => {
    const key = 'kexi.theme';
    const valid = value => ['light', 'dark', 'system'].includes(value);
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    let mode = 'system';
    try { const saved = localStorage.getItem(key); if (valid(saved)) mode = saved; } catch {}
    const apply = () => {
        const resolved = mode === 'system' ? (media.matches ? 'dark' : 'light') : mode;
        document.documentElement.dataset.theme = resolved;
        document.documentElement.style.colorScheme = resolved;
        window.dispatchEvent(new CustomEvent('kexi:theme', { detail: { mode, resolved } }));
    };
    window.kexiTheme = {
        get mode() { return mode; },
        set(value) {
            if (!valid(value) || value === mode) return;
            mode = value;
            try { localStorage.setItem(key, mode); } catch {}
            apply();
        },
    };
    if (media.addEventListener) media.addEventListener('change', () => { if (mode === 'system') apply(); });
    else media.addListener(() => { if (mode === 'system') apply(); });
    window.addEventListener('storage', event => {
        if (event.key !== key && event.key !== null) return;
        mode = valid(event.newValue) ? event.newValue : 'system';
        apply();
    });
    apply();
})();
</script>
