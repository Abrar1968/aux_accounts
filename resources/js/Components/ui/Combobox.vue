<script setup>
// Async combobox for /ajax pickers. `fetcher(q)` returns options [{value,label,meta}].
import { ref, watch } from 'vue';
import Field from './Field.vue';

const props = defineProps({ label: String, error: String, placeholder: String, fetcher: { type: Function, required: true }, display: String });
const model = defineModel(); // selected option object
const id = `cb-${Math.random().toString(36).slice(2, 9)}`;
const q = ref(props.display ?? '');
const items = ref([]);
const open = ref(false);
const active = ref(0);
let t;
async function load() {
    try { items.value = await props.fetcher(q.value); active.value = 0; } catch { items.value = []; }
}
watch(q, () => { clearTimeout(t); t = setTimeout(load, 200); });
const blur = () => setTimeout(() => (open.value = false), 150);
function pick(o) { model.value = o; q.value = o.label; open.value = false; }
function onKey(e) {
    if (!open.value) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, items.value.length - 1); }
    if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    if (e.key === 'Enter' && items.value[active.value]) { e.preventDefault(); pick(items.value[active.value]); }
    if (e.key === 'Escape') open.value = false;
}
</script>

<template>
    <Field :label="label" :error="error" :for="id">
        <div class="relative">
            <input :id="id" v-model="q" type="text" role="combobox" :aria-expanded="open" :placeholder="placeholder" autocomplete="off"
                :class="error && '!border-negative'" class="field px-3"
                @focus="open = true; load()" @blur="blur" @keydown="onKey" />
            <ul v-if="open && items.length" role="listbox" class="glass-pop absolute z-40 mt-1.5 max-h-64 w-full overflow-y-auto rounded-2xl p-1.5">
                <li v-for="(o, i) in items" :key="o.value" role="option" :aria-selected="i === active" @mousedown.prevent="pick(o)"
                    :class="i === active ? 'bg-brand-50 text-ink' : ''" class="cursor-pointer rounded-xl px-3 py-2 text-sm">
                    <p class="truncate font-medium">{{ o.label }}</p><p v-if="o.meta" class="truncate text-xs text-ink-3">{{ o.meta }}</p>
                </li>
            </ul>
        </div>
    </Field>
</template>
