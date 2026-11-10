import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/** Run `open` when the URL carries ?new=1 — on first load and when a shortcut (Ctrl+N, Ctrl+P…) re-visits the same page. */
export function useOpenOnNew(open) {
    const page = usePage();
    const wants = (url) => new URLSearchParams(String(url ?? '').split('?')[1] ?? '').get('new') === '1';
    onMounted(() => { if (wants(page.url)) open(); });
    watch(() => page.url, (url, prev) => { if (url !== prev && wants(url)) open(); });
}
