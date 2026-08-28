<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    title: {
        type: String,
        default: 'Quick actions',
    },
    eyebrow: {
        type: String,
        default: 'Shortcuts',
    },
    actions: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section aria-labelledby="quick-actions-title">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-black tracking-[0.14em] text-emerald-700 uppercase">
                    {{ eyebrow }}
                </p>
                <h2
                    id="quick-actions-title"
                    class="mt-1 text-xl font-black tracking-tight text-slate-950"
                >
                    {{ title }}
                </h2>
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <template v-for="action in actions" :key="action.key">
                <a
                    v-if="action.available && action.href"
                    :href="action.href"
                    class="group rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 transition group-hover:bg-emerald-700 group-hover:text-white"
                    >
                        <svg viewBox="0 0 24 24" class="size-5 fill-none stroke-current stroke-2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M18.4 5.6L5.6 18.4"
                            />
                        </svg>
                    </span>
                    <span
                        class="mt-4 flex items-center justify-between gap-3 font-bold text-slate-950"
                        >{{ action.label }}
                        <span class="text-emerald-700 transition group-hover:translate-x-1"
                            >→</span
                        ></span
                    >
                    <span class="mt-1 block text-sm leading-6 text-slate-600">{{
                        action.description
                    }}</span>
                </a>
                <div
                    v-else
                    class="rounded-xl border border-slate-200 bg-slate-100 p-5"
                    aria-disabled="true"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-slate-700">{{ action.label }}</span>
                        <span
                            class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600"
                        >
                            {{ t('quickActions.comingLater') }}
                        </span>
                    </div>
                    <span class="mt-1 block text-sm leading-6 text-slate-600">{{
                        action.description
                    }}</span>
                </div>
            </template>
        </div>
    </section>
</template>
