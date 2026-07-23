<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    eyebrow: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <header class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-2 text-sm font-semibold text-emerald-700">{{ eyebrow }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-3">
            <slot name="actions" />
        </div>
    </header>
</template>
