<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
    eyebrow: { type: String, default: 'Website content' },
    open: { type: Boolean, default: false },
});

const panel = ref(null);

function revealHashTarget() {
    if (window.location.hash === `#${props.id}` && panel.value) {
        panel.value.open = true;
    }
}

onMounted(() => {
    revealHashTarget();
    window.addEventListener('hashchange', revealHashTarget);
});

onBeforeUnmount(() => window.removeEventListener('hashchange', revealHashTarget));
</script>

<template>
    <details
        :id="id"
        ref="panel"
        :open="open"
        class="group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <summary
            class="cursor-pointer list-none p-5 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-inset sm:p-6"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">
                        {{ eyebrow }}
                    </p>
                    <h2 class="mt-1 text-lg font-bold text-slate-950 sm:text-xl">{{ title }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">
                        {{ description }}
                    </p>
                </div>
                <span
                    aria-hidden="true"
                    class="mt-1 flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition group-open:rotate-90 group-open:bg-emerald-100 group-open:text-emerald-800"
                >
                    ▶
                </span>
            </div>
        </summary>
        <div class="border-t border-slate-200 p-5 sm:p-6">
            <slot />
        </div>
    </details>
</template>
