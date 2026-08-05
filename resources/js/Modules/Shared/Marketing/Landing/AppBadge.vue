<script setup>
defineProps({
    tone: { type: String, default: 'subtle' }, // 'subtle' | 'inverse'
});
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-bold tracking-wide uppercase"
        :class="
            tone === 'inverse' ? 'bg-white/10 text-emerald-100' : 'bg-emerald-50 text-emerald-800'
        "
    >
        <slot />
    </span>
</template>
