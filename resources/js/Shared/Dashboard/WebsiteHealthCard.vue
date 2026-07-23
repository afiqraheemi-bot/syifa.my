<script setup>
defineProps({
    status: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <h2 class="font-bold text-slate-950">{{ status.title }}</h2>
            <span
                :class="[
                    'size-3 rounded-full',
                    status.tone === 'positive' ? 'bg-emerald-500' : 'bg-slate-300',
                ]"
                aria-hidden="true"
            />
        </div>
        <p class="mt-4 text-xl font-bold text-slate-950">{{ status.value }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ status.detail }}</p>
    </article>
</template>
