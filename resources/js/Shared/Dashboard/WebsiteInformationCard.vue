<script setup>
defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <article
        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-950/5 sm:p-6"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xs font-black tracking-[0.12em] text-slate-500 uppercase">
                {{ status.title }}
            </h2>
            <span
                :class="[
                    'size-2 rounded-full ring-4',
                    status.tone === 'positive'
                        ? 'bg-emerald-500 ring-emerald-50'
                        : 'bg-amber-400 ring-amber-50',
                ]"
            />
        </div>
        <p
            class="mt-4 truncate text-xl font-black tracking-tight text-slate-950"
            :title="status.value"
        >
            {{ status.value }}
        </p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ status.detail }}</p>
        <a
            v-if="status.url && status.actionLabel"
            :href="status.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 inline-flex min-h-11 items-center gap-1 rounded-xl text-sm font-bold text-emerald-700 transition hover:text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
        >
            {{ status.actionLabel }} <span aria-hidden="true">↗</span>
        </a>
    </article>
</template>
