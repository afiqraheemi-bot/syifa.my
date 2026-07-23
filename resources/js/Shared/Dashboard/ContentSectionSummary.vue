<script setup>
defineProps({
    section: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 class="font-bold text-slate-950">{{ section.title }}</h2>
            <span
                :class="[
                    'rounded-full px-2.5 py-1 text-xs font-bold',
                    section.complete
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-slate-100 text-slate-600',
                ]"
            >
                {{ section.status }}
            </span>
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-600">{{ section.detail }}</p>
        <p class="mt-2 text-xs font-semibold text-slate-500">
            {{ section.enabled ? 'Enabled' : 'Not enabled' }}
        </p>
    </article>
</template>
