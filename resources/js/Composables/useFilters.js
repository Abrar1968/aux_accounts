import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/** URL-synced list filters (shareable, back-button safe). Debounced for text search. */
export function useFilters(initial = {}, { only, debounce = 300 } = {}) {
    const filters = reactive({ ...initial });
    let t;
    watch(filters, () => {
        clearTimeout(t);
        t = setTimeout(() => {
            const query = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined));
            router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true, replace: true, only });
        }, debounce);
    }, { deep: true });
    const reset = () => Object.keys(filters).forEach((k) => (filters[k] = ''));
    return { filters, reset };
}
