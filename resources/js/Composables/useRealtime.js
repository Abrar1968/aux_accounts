import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToasts } from './useToasts';

/**
 * Pusher via Laravel Echo: ledger/CRM events refresh the current page's props; personal
 * notifications raise a toast and bump the bell badge (docs/07 §5).
 */
export function useRealtime(user, onNotification) {
    const { push } = useToasts();
    let joined = false;
    onMounted(() => {
        if (!window.Echo || !user.value) return;
        joined = true;
        window.Echo.private('auxtech')
            .listen('.ledger.changed', () => router.reload({ preserveScroll: true, preserveState: true }))
            .listen('.lead.updated', () => router.reload({ preserveScroll: true, preserveState: true }))
            .listen('.period.closed', () => router.reload({ preserveScroll: true }))
            .listen('.automation.finished', (e) => push({ type: 'success', title: 'Automation finished', message: e.command }));
        window.Echo.private(`App.Models.User.${user.value.id}`).notification((n) => {
            onNotification?.(n);
            push({ type: n.level ?? 'info', title: n.title, message: n.message, timeout: n.level === 'error' ? 0 : 6000 });
        });
    });
    onBeforeUnmount(() => {
        if (!joined) return;
        window.Echo.leave('auxtech');
        window.Echo.leave(`App.Models.User.${user.value.id}`);
    });
}
