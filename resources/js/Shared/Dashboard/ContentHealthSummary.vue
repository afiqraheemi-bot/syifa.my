<script setup>
import { computed } from 'vue';

const props = defineProps({
    health: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
});

const incompleteSections = computed(() => props.sections.filter((section) => !section.complete));
</script>

<template>
    <details
        :open="health.tone !== 'positive'"
        :class="[
            'group overflow-hidden rounded-[1.75rem] border shadow-sm',
            health.tone === 'positive'
                ? 'border-emerald-200 bg-emerald-50'
                : 'border-amber-200 bg-amber-50',
        ]"
    >
        <summary
            class="cursor-pointer list-none p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 sm:p-6"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-800">
                        Kesihatan kandungan
                    </p>
                    <p class="mt-1 font-bold text-slate-950">{{ health.value }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-bold"
                        :class="
                            health.tone === 'positive'
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-amber-100 text-amber-900'
                        "
                    >
                        {{ health.tone === 'positive' ? 'Sedia' : 'Perlu tindakan' }}
                    </span>
                    <span aria-hidden="true" class="text-slate-500 transition group-open:rotate-90">
                        ▶
                    </span>
                </div>
            </div>
        </summary>
        <div class="border-t border-slate-200 px-5 py-5 sm:px-6">
            <p class="text-sm leading-6 text-slate-600">{{ health.detail }}</p>
            <div v-if="incompleteSections.length" class="mt-4 flex flex-wrap gap-2">
                <span
                    v-for="section in incompleteSections"
                    :key="section.key"
                    class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900"
                >
                    {{ section.title }} · {{ section.status }}
                </span>
            </div>
            <p v-else class="mt-3 text-sm font-semibold text-emerald-700">
                Semua section aktif mempunyai maklumat yang diperlukan.
            </p>
        </div>
    </details>
</template>
