<script setup>
defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-2xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
        <p class="text-sm font-semibold text-emerald-300">Website overview</p>
        <h2 class="mt-2 text-2xl font-bold tracking-tight">{{ status.title }}</h2>
        <p class="mt-4 text-lg font-semibold">{{ status.value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-300">{{ status.detail }}</p>
    </article>
</template>
