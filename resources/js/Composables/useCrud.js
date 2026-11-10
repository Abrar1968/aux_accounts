import { reactive, ref } from 'vue';
import { useOpenOnNew } from '@/Composables/useOpenOnNew';
import { router, useForm } from '@inertiajs/vue3';

/**
 * Create / edit drawer + delete confirmation for a resource.
 *   const crud = useCrud({ base: '/accounting/capital', blank: {...}, toForm: (row) => ({...}) });
 * - crud.create() / crud.edit(row) open the drawer with a fresh or pre-filled form
 * - crud.submit() POSTs (create) or PUTs `${base}/${id}` (edit)
 * - crud.askDelete(row) + <ConfirmDialog v-model="crud.confirm.open" @confirm="crud.doDelete"> deletes
 *   (`reason: true` sends the typed reason, required for financial records → audit log)
 * - ?new=1 in the URL opens the create drawer (top-bar "Create" menu)
 */
export function useCrud({ base, blank, toForm = (r) => r, reason = true, forceFormData = false, deleteUrl = null }) {
    const open = ref(false);
    const editing = ref(null);
    const form = useForm({ ...blank });

    function create(extra = {}) {
        editing.value = null;
        form.defaults({ ...blank, ...extra });
        form.reset();
        form.clearErrors();
        open.value = true;
    }
    function edit(row) {
        editing.value = row;
        const values = toForm(row);
        Object.keys(blank).forEach((k) => (form[k] = values[k] ?? blank[k]));
        form.clearErrors();
        open.value = true;
    }
    function submit(opts = {}) {
        const o = { preserveScroll: true, forceFormData: forceFormData && !editing.value, onSuccess: () => (open.value = false), ...opts };
        editing.value ? form.put(`${base}/${editing.value.id}`, o) : form.post(base, o);
    }

    const confirm = reactive({ open: false, row: null, loading: false, reason });
    function askDelete(row) { confirm.row = row; confirm.open = true; }
    function doDelete(text) {
        confirm.loading = true;
        router.delete(deleteUrl ? deleteUrl(confirm.row) : `${base}/${confirm.row.id}`, {
            data: reason ? { reason: text } : {}, preserveScroll: true,
            onFinish: () => { confirm.loading = false; confirm.open = false; },
        });
    }

    useOpenOnNew(create);

    return { open, editing, form, create, edit, submit, confirm, askDelete, doDelete };
}
