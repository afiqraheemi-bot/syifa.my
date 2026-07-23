<script setup>
defineProps({
    health: { type: Object, required: true },
});
</script>

<template>
    <article class="rounded-2xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">{{ health.title }}</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight">{{ health.value }}</h2>
            </div>
            <span
                :class="[
                    'size-3 rounded-full',
                    health.tone === 'positive' ? 'bg-emerald-400' : 'bg-amber-300',
                ]"
                aria-hidden="true"
            />
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-300">{{ health.detail }}</p>
    </article>
</template>
