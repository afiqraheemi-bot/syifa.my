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
    url: {
        type: String,
        default: null,
    },
    actionLabel: {
        type: String,
        default: null,
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
        <a
            v-if="url && actionLabel"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 inline-flex min-h-11 items-center rounded-lg font-bold text-emerald-700 underline decoration-emerald-300 underline-offset-4 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
            {{ actionLabel }}
            <span class="sr-only"> (opens in a new tab)</span>
        </a>
    </article>
</template>
