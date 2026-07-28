<script setup>
defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-slate-600">{{ status.title }}</h2>
        <p class="mt-3 text-lg font-bold text-slate-950">{{ status.value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ status.detail }}</p>
        <a
            v-if="status.url && status.actionLabel"
            :href="status.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
        >
            {{ status.actionLabel }}
        </a>
    </article>
</template>
