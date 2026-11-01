import { reactive } from 'vue';

// Global toast queue. Fed by Inertia flash (server) and Echo events (realtime).
const state = reactive({ items: [] });
let seq = 0;

export function useToasts() {
    function push({ type = 'info', title = '', message = '', timeout = 5000 }) {
        const id = ++seq;
        state.items.push({ id, type, title, message });
        if (timeout) setTimeout(() => dismiss(id), timeout);
        return id;
    }
    function dismiss(id) {
        const i = state.items.findIndex((t) => t.id === id);
        if (i >= 0) state.items.splice(i, 1);
    }
    return { toasts: state.items, push, dismiss };
}
