export function registerTheme(Alpine) {
    Alpine.store('theme', {
        mode: window.kexiTheme?.mode || 'system',
        set(mode) { window.kexiTheme?.set(mode); },
    });
    window.addEventListener('kexi:theme', event => {
        Alpine.store('theme').mode = event.detail.mode;
    });
}
