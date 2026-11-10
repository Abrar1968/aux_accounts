<template>
    <!-- Sticky action bar for phones; inline on larger screens. -->
    <div class="no-print fixed inset-x-0 bottom-0 z-20 flex gap-2 glass-chrome border-t border-line px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:hidden [&>*]:flex-1">
        <slot />
    </div>
    <div class="h-16 sm:hidden" aria-hidden="true" />
</template>
