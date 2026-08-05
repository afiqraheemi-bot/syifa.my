<script setup>
defineProps({
    number: { type: Number, required: true },
    icon: { type: String, required: true },
    title: { type: String, required: true },
    body: { type: String, required: true },
    last: { type: Boolean, default: false },
});
</script>

<template>
    <li data-reveal class="reveal relative flex gap-5 pb-10 last:pb-0">
        <div class="flex flex-col items-center">
            <span
                class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-sm font-black text-white"
            >
                {{ number }}
            </span>
            <span
                v-if="!last"
                class="mt-2 w-px flex-1 border-l-2 border-dashed border-emerald-200"
            />
        </div>
        <div
            class="flex flex-1 items-start justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm shadow-slate-900/5"
        >
            <div>
                <h3 class="font-bold text-slate-950">{{ title }}</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">{{ body }}</p>
            </div>
            <span
                class="hidden size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 sm:flex"
            >
                <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-[1.75]">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
                </svg>
            </span>
        </div>
    </li>
</template>
