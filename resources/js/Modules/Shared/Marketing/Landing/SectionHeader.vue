<script setup>
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    align: { type: String, default: 'left' }, // 'left' | 'center'
    tone: { type: String, default: 'default' }, // 'default' | 'inverse'
});
</script>

<template>
    <div
        data-reveal
        class="reveal"
        :class="align === 'center' ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'"
    >
        <p
            v-if="eyebrow"
            class="text-xs font-bold tracking-[0.2em] uppercase"
            :class="tone === 'inverse' ? 'text-emerald-300' : 'text-emerald-700'"
        >
            {{ eyebrow }}
        </p>
        <h2
            class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl"
            :class="tone === 'inverse' ? 'text-white' : 'text-slate-950'"
        >
            {{ title }}
        </h2>
        <p
            v-if="subtitle"
            class="mt-4 text-base leading-7"
            :class="tone === 'inverse' ? 'text-emerald-100/80' : 'text-slate-600'"
        >
            {{ subtitle }}
        </p>
    </div>
</template>
