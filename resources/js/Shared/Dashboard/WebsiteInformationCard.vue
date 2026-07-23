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
    </article>
</template>
