<script setup>
defineProps({
    question: { type: String, required: true },
    answer: { type: String, required: true },
    open: { type: Boolean, default: false },
    panelId: { type: String, required: true },
});

defineEmits(['toggle']);
</script>

<template>
    <div data-reveal class="reveal border-b border-slate-100">
        <h3>
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-4 py-4 text-left text-base font-bold text-slate-950 transition hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                :aria-expanded="open"
                :aria-controls="panelId"
                @click="$emit('toggle')"
            >
                {{ question }}
                <svg
                    viewBox="0 0 24 24"
                    class="size-5 shrink-0 fill-none stroke-current stroke-2 transition-transform duration-300"
                    :class="open ? 'rotate-45' : ''"
                >
                    <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                </svg>
            </button>
        </h3>
        <div
            :id="panelId"
            class="grid transition-all duration-300 ease-out"
            :style="{ gridTemplateRows: open ? '1fr' : '0fr' }"
        >
            <div class="overflow-hidden">
                <p class="pb-5 text-sm leading-6 text-slate-600">{{ answer }}</p>
            </div>
        </div>
    </div>
</template>
