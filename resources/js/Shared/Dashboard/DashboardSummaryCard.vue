<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    detail: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'neutral',
    },
});
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-sm font-semibold text-slate-600">{{ label }}</h2>
            <span
                :class="[
                    'size-2.5 shrink-0 rounded-full',
                    tone === 'positive' ? 'bg-emerald-500' : 'bg-slate-300',
                ]"
                aria-hidden="true"
            />
        </div>
        <p class="mt-3 text-xl font-bold tracking-tight text-slate-950">{{ value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ detail }}</p>
    </article>
</template>
