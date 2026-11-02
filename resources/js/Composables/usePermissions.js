import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { can as canFor } from '@/lib/nav';

export function usePermissions() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user);
    return { user, can: (perm) => canFor(user.value, perm) };
}
