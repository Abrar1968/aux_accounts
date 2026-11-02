import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Measured width so SVG text stays ~10px on phones instead of scaling down with a fixed viewBox. */
export function useChartWidth(fallback = 640) {
    const el = ref(null);
    const width = ref(fallback);
    let ro;
    onMounted(() => {
        if (!el.value || !window.ResizeObserver) return;
        ro = new ResizeObserver(([e]) => { width.value = Math.max(280, Math.round(e.contentRect.width)); });
        ro.observe(el.value);
    });
    onBeforeUnmount(() => ro?.disconnect());
    return { el, width };
}
