<script setup>
defineProps({
    label: {
        type: String,
        default: 'Loading',
    },
    rows: {
        type: Number,
        default: 3,
    },
});
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200 bg-white p-6"
        role="status"
        aria-live="polite"
    >
        <span class="sr-only">{{ label }}</span>
        <div class="animate-pulse space-y-4" aria-hidden="true">
            <div class="h-5 w-40 rounded bg-slate-200" />
            <div v-for="row in rows" :key="row" class="h-12 rounded-xl bg-slate-100" />
        </div>
    </section>
</template>
