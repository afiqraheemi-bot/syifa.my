<script setup>
defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <article
        :class="[
            'rounded-[1.75rem] border p-6 shadow-sm',
            status.tone === 'positive'
                ? 'border-emerald-200 bg-emerald-50'
                : 'border-amber-200 bg-amber-50',
        ]"
    >
        <div class="flex items-start justify-between gap-4">
            <span
                :class="[
                    'flex size-10 items-center justify-center rounded-xl',
                    status.tone === 'positive'
                        ? 'bg-emerald-700 text-white'
                        : 'bg-amber-200 text-amber-900',
                ]"
                ><svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 13h3l2-6 4 12 2-6h5"
                    /></svg
            ></span>
            <span
                :class="[
                    'mt-1 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide',
                    status.tone === 'positive'
                        ? 'bg-white text-emerald-700'
                        : 'bg-white text-amber-800',
                ]"
                aria-hidden="true"
                >{{ status.tone === 'positive' ? 'Sihat' : 'Perlu semakan' }}</span
            >
        </div>
        <h2 class="mt-5 text-xs font-black tracking-[0.14em] text-slate-600 uppercase">
            {{ status.title }}
        </h2>
        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ status.value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ status.detail }}</p>
    </article>
</template>
