import { onBeforeUnmount, onMounted } from 'vue';

/** useHotkey('k', handler, { meta: true }) — Ctrl on Windows/Linux, ⌘ on macOS. */
export function useHotkey(key, handler, { meta = false } = {}) {
    const fn = (e) => {
        if (meta && !(e.ctrlKey || e.metaKey)) return;
        if (!meta && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target?.tagName)) return;
        if (e.key.toLowerCase() === key) { e.preventDefault(); handler(e); }
    };
    onMounted(() => window.addEventListener('keydown', fn));
    onBeforeUnmount(() => window.removeEventListener('keydown', fn));
}
