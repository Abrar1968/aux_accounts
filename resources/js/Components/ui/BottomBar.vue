<template>
    <!-- Sticky action bar for phones; inline on larger screens. -->
    <div class="no-print fixed inset-x-0 bottom-0 z-20 flex gap-2 border-t border-line bg-surface/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:hidden [&>*]:flex-1">
        <slot />
    </div>
    <div class="h-16 sm:hidden" aria-hidden="true" />
</template>
