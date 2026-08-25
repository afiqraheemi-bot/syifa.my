<script setup>
defineProps({
    summaryKey: {
        type: String,
        default: 'summary',
    },
    positiveLabel: {
        type: String,
        default: 'Active',
    },
    neutralLabel: {
        type: String,
        default: 'Review',
    },
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
    <article
        class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-950/5 sm:p-6"
    >
        <div class="flex items-start justify-between gap-4">
            <span
                :class="[
                    'flex size-10 shrink-0 items-center justify-center rounded-xl transition',
                    tone === 'positive'
                        ? 'bg-emerald-100 text-emerald-700 group-hover:bg-emerald-700 group-hover:text-white'
                        : 'bg-slate-100 text-slate-500',
                ]"
                aria-hidden="true"
            >
                <svg
                    v-if="summaryKey === 'bookings'"
                    viewBox="0 0 24 24"
                    class="size-5 fill-none stroke-current stroke-2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1zM8 13h3M8 17h6"
                    />
                </svg>
                <svg
                    v-else-if="summaryKey === 'website'"
                    viewBox="0 0 24 24"
                    class="size-5 fill-none stroke-current stroke-2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 5h16v14H4zM4 9h16M8 7h.01M11 7h.01"
                    />
                </svg>
                <svg
                    v-else-if="summaryKey === 'subscription'"
                    viewBox="0 0 24 24"
                    class="size-5 fill-none stroke-current stroke-2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 7h16v10H4zM4 10h16M7 14h4"
                    />
                </svg>
                <svg v-else viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 3l8 5v12H4V8l8-5zM9 20v-6h6v6"
                    />
                </svg>
            </span>
            <span
                :class="[
                    'mt-1 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide',
                    tone === 'positive'
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-slate-100 text-slate-500',
                ]"
            >
                <span
                    :class="[
                        'size-1.5 rounded-full',
                        tone === 'positive' ? 'bg-emerald-500' : 'bg-slate-400',
                    ]"
                />
                {{ tone === 'positive' ? positiveLabel : neutralLabel }}
            </span>
        </div>
        <h2 class="mt-5 text-xs font-black tracking-[0.12em] text-slate-500 uppercase">
            {{ label }}
        </h2>
        <p class="mt-2 truncate text-2xl font-black tracking-[-0.03em] text-slate-950">
            {{ value }}
        </p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ detail }}</p>
        <a
            v-if="url && actionLabel"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 inline-flex min-h-10 items-center gap-1 rounded-lg text-sm font-bold text-emerald-700 hover:text-emerald-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        >
            {{ actionLabel }}
            <span aria-hidden="true">↗</span>
            <span class="sr-only"> (opens in a new tab)</span>
        </a>
    </article>
</template>
