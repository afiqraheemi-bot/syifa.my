<script setup>
defineProps({
    icon: { type: String, required: true },
    title: { type: String, required: true },
    body: { type: String, required: true },
});
</script>

<template>
    <article
        data-reveal
        class="reveal group rounded-2xl border border-slate-100 bg-white p-7 shadow-sm shadow-slate-900/5 transition duration-300 hover:-translate-y-1 hover:border-emerald-100 hover:shadow-lg hover:shadow-emerald-900/10"
    >
        <span
            class="flex size-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 transition group-hover:bg-emerald-700 group-hover:text-white"
        >
            <svg viewBox="0 0 24 24" class="size-6 fill-none stroke-current stroke-[1.75]">
                <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
            </svg>
        </span>
        <h3 class="mt-5 text-lg font-bold text-slate-950">{{ title }}</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ body }}</p>
    </article>
</template>
